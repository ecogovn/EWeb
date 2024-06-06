<?php

namespace App\Helpers\Rides;

use Kreait\Firebase\Contract\Database;
use Sk\Geohash\Geohash;
use Carbon\Carbon;
use App\Models\Request\RequestMeta;
use Illuminate\Support\Facades\DB;
use App\Models\Request\Request;
use Illuminate\Support\Facades\Log;
use App\Base\Constants\Setting\Settings;
use App\Models\Admin\Driver;
use App\Jobs\Notifications\SendPushNotification;
use App\Models\Request\DriverRejectedRequest;
use App\Models\Request\RequestCycles;
use App\Jobs\NoDriverFoundNotifyJob;
use App\Models\Admin\ZoneSurgePrice;
use App\Models\Request\RequestCancellationFee;
use App\Models\Admin\PromoUser;

trait RidePriceCalculationHelpers
{



    /**
     * Calculate Ride fare
     * pick lat,pick lng, drop lat, drop lng should be double
     * total_distance can be double
     * duration should be in integer and in mins
     * 
     */
    //
    protected function calculateBillForARide($pick_lat,$pick_lng,$drop_lat,$drop_lng,$total_distance, $duration, $zone_type, $type_prices, $coupon_detail,$timezone,$user_id,$waiting_time,$request_detail,$driver)
    {

        /**
         * Calculate Airport Surge Fee starts here
         * 
         * */
        $airport_surge = find_airport($pick_lat,$pick_lng);
        
        if($airport_surge==null){
            $airport_surge = find_airport($drop_lat,$drop_lng);
        }

        $airport_surge_fee = 0;

        if($airport_surge){

            $airport_surge_fee = $airport_surge->airport_surge_fee?:0;

        }

        /**
         * Airport Surhe Fee calculation ends here
         * */

        /**
         * Distance price calculation starts here
         * 
         * */

        // Pricing Parameters
        $price_per_distance = $type_prices->price_per_distance;

        $base_distance = $type_prices->base_distance;

        $calculatable_distance = ($total_distance - $base_distance);

        if($calculatable_distance < 0 ){

            $calculatable_distance = 0;
        }

        // Calculate Surge price 
        $current_time = Carbon::now()->setTimezone($timezone);
        $day = $current_time->dayName;
        $current_time = $current_time->toTimeString();

        $zone_surge_price = ZoneSurgePrice::whereZoneId($zone_type->zone_id)->where('day',$day)->whereTime('start_time','<=',$current_time)->whereTime('end_time','>=',$current_time)->first();
        if($zone_surge_price){
            
            $surge_percent = $zone_surge_price->value;

            $surge_price_additional_cost = ($price_per_distance * ($surge_percent / 100));

            $price_per_distance += $surge_price_additional_cost;

        }


         // Base price
        $base_price = $type_prices->base_price;

         // Time price
         $time_price = $duration * $type_prices->price_per_time;

        // Distance Price
        $distance_price = ($calculatable_distance * $price_per_distance);

         // Waiting charge
        $waiting_charge = $waiting_time * $type_prices->waiting_charge;


        $sub_total = $base_price + $time_price + $distance_price + $waiting_charge + $airport_surge_fee;


        // Calculate Ride Cancellation Fee

        

       
        /**
         * Apply Coupon
         * 
         * */

        $discount_amount = 0;
        $coupon_applied_sub_total = $sub_total;

        if ($coupon_detail) {
            if ($coupon_detail->minimum_trip_amount < $sub_total) {
                $discount_amount = $sub_total * ($coupon_detail->discount_percent/100);
                if ($coupon_detail->maximum_discount_amount>0 && $discount_amount > $coupon_detail->maximum_discount_amount) {
                    $discount_amount = $coupon_detail->maximum_discount_amount;
                }
                $coupon_applied_sub_total = $sub_total - $discount_amount;

                if($request_detail){
                $promo = new PromoUser();
                $promo->promo_code_id = $coupon_detail->id;
                $promo->user_id = $request_detail->user_id;
                $promo->request_id = $request_detail->id;
                $promo->save();  
                }
                

            }else{

            if($request_detail){
                $promoUsers =  PromoUser::where('request_id', $request_detail->id)->delete();
            }



            }
        }


        // Apply coupon ends here

        if($request_detail){

            if($request_detail->is_bid_ride){
                
                $sub_total=$request_detail->accepted_ride_fare;
            }

        
        $cancellation_fee = RequestCancellationFee::where('user_id',$request_detail->user_id)->where('is_paid',0)->sum('cancellation_fee');

        $sub_total += $cancellation_fee;


        if($cancellation_fee >0){

            RequestCancellationFee::where('user_id',$request_detail->user_id)->update([
                'is_paid'=>1,
                'paid_request_id'=>$request_detail->id]);


        }

        }

        

        // Get service tax percentage from settings
        $tax_percent = get_settings('service_tax');
        
        $tax_amount = ($sub_total * ($tax_percent / 100));

        // Get Admin Commision
        $admin_commision_type = get_settings('admin_commission_type');
        $admin_commission_type_for_driver = get_settings('admin_commission_type_for_driver');

        // Convenience fee for customer
        $service_fee = get_settings('admin_commission');


        // These lines for ETA response
        $discount_admin_commision = ($coupon_applied_sub_total * ($service_fee / 100));
        $discount_tax_amount = $coupon_applied_sub_total * ($tax_percent / 100);
        $discounted_total_price = $coupon_applied_sub_total + $discount_tax_amount + $discount_admin_commision;


if($driver && $driver->owner_id != NULL){

    $admin_commission_type_for_driver = get_settings('admin_commission_type_for_owner');
    $service_fee_for_driver = get_settings('admin_commission_for_owner');
        }
    else {
    $admin_commission_type_for_driver = get_settings('admin_commission_type_for_driver');
    $service_fee_for_driver = get_settings('admin_commission_for_driver');

}


        if($admin_commision_type==1){

        $admin_commision = ($sub_total * ($service_fee / 100));

        if($request_detail && $request_detail->is_bid_ride){

            $sub_total -=$admin_commision;

            $sub_total -=$tax_amount;


        }

        }else{

            $admin_commision = $service_fee;

            if($request_detail && $request_detail->is_bid_ride){

                $sub_total -=$admin_commision;

                $sub_total -=$tax_amount;


            }


        }
        // Admin commision with tax amount
        $admin_commision_with_tax = $tax_amount + $admin_commision;
        $driver_commision = $sub_total+$discount_amount;

        // Driver Commission
        if($coupon_detail && $coupon_detail->deduct_from==2){
            $driver_commision = $sub_total;
        }

        if($admin_commission_type_for_driver==1){

        $admin_commision_from_driver = ($driver_commision * ($service_fee_for_driver / 100));

        }else{

            $admin_commision_from_driver = $service_fee_for_driver;

        }

        $driver_commision -= $admin_commision_from_driver;

        // Total Amount
        $total_amount = $sub_total + $admin_commision_with_tax;

        $admin_commision_with_tax += $admin_commision_from_driver;

        $pickup_duration = 0;
        $dropoff_duration = $duration;
        $wait_duration = 0;
        $duration = $pickup_duration + $dropoff_duration + $wait_duration;

        if($request_detail){

        return $result = [
        'base_price'=>ceil($base_price),
        'base_distance'=>$type_prices->base_distance,
        'price_per_distance'=>$type_prices->price_per_distance,
        'distance_price'=>ceil($distance_price),
        'price_per_time'=>$type_prices->price_per_time,
        'time_price'=>ceil($time_price),
        'promo_discount'=>ceil($discount_amount),
        'waiting_charge'=>ceil($waiting_charge),
        'service_tax'=>ceil($tax_amount),
        'service_tax_percentage'=>$tax_percent,
        'admin_commision'=>ceil($admin_commision),
        'admin_commision_with_tax'=>ceil($admin_commision_with_tax),
        'driver_commision'=>ceil($driver_commision),
        'admin_commision_from_driver'=>ceil($admin_commision_from_driver),
        'total_amount'=>ceil($total_amount),
        'total_distance'=>$total_distance,
        'total_time'=>$duration,
        'airport_surge_fee'=>ceil($airport_surge_fee),
        'cancellation_fee'=>ceil($cancellation_fee)
        ];


        }

        // return calculated params for eta
        return (object)[
                'distance' => ceil($total_distance),
                'base_distance' => $base_distance,
                'base_price' => ceil($base_price),
                'price_per_distance' => $type_prices->price_per_distance,
                'price_per_time' => $type_prices->price_per_time,
                'distance_price' => ceil($distance_price),
                'time_price' => ceil($time_price),
                'subtotal_price' => $sub_total,
                'tax_percent' => $tax_percent,
                'tax_amount' => $tax_amount,
                'discount_total_tax_amount'=>$discount_tax_amount,
                'without_discount_admin_commision'=>$admin_commision,
                'discount_admin_commision'=>$discount_admin_commision,
                'total_price' => ceil($total_amount),
                'discounted_total_price'=>$discounted_total_price,
                'discount_amount'=>$discount_amount,
                'pickup_duration' => ceil($pickup_duration),
                'dropoff_duration' => ceil($dropoff_duration),
                'wait_duration' => ceil($wait_duration),
                'duration' => ceil($duration),
                'airport_surge_fee'=>$airport_surge_fee
            ];




    }

    
}
