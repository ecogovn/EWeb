<?php

namespace App\Http\Controllers\Api\V1\Common;

use App\Models\Master\CarMake;
use App\Models\Master\CarModel;
use App\Http\Controllers\Api\V1\BaseController;
use Carbon\Carbon;
use Sk\Geohash\Geohash;
use Illuminate\Http\Request;
use Kreait\Firebase\Contract\Database;
use App\Helpers\Rides\FetchDriversFromFirebaseHelpers;
use App\Models\Admin\DriverAvailability;
use Illuminate\Support\Facades\DB;
use Config;

/**
 * @group Vehicle Management
 *
 * APIs for vehilce management apis. i.e types,car makes,models apis
 */
class CarMakeAndModelController extends BaseController
{
    use FetchDriversFromFirebaseHelpers;

    protected $car_make;
    protected $car_model;

    public function __construct(CarMake $car_make, CarModel $car_model,Database $database)
    {
        $this->car_make = $car_make;
        $this->car_model = $car_model;
        $this->database = $database;

    }

    /**
    * Get All Car makes
    *
    */
    public function getCarMakes()
    { 
         $transport_type = request()->transport_type;

        // return $this->respondSuccess($this->car_make->active()->where('transport_type',$transport_type)->where('vehicle_make_for',request()->vehicle_type)->orderBy('name')->get());
        if(request()->has('transport_type') && config('app.app_for') !== "taxi" && config('app.app_for') !== "delivery"){

        return $this->respondSuccess($this->car_make->active()->where('transport_type',$transport_type)->where('vehicle_make_for',request()->vehicle_type)->orderBy('name')->get());

        }else{
            return $this->respondSuccess($this->car_make->active()->where('vehicle_make_for',request()->vehicle_type)->orderBy('name')->get());
        }
    }

   

    /**
    * Get Car models by make id
    * @urlParam make_id  required integer, make_id provided by user
    */
    public function getCarModels($make_id)
    {
        return $this->respondSuccess($this->car_model->where('make_id', $make_id)->active()->orderBy('name')->get());
    }

    public function getAppModule()
    {

        $enable_owner_login =  get_settings('shoW_owner_module_feature_on_mobile_app');

        $enable_email_otp =  get_settings('shoW_email_otp_feature_on_mobile_app');
     
        $firebase_otp_enabled =  get_sms_settings('enable_firebase_otp');

        $firebase_otp = false;

        if($firebase_otp_enabled==1)
        {
            $firebase_otp = true;
        }


        return response()->json(['success'=>true,"message"=>'success','enable_owner_login'=>$enable_owner_login,'enable_email_otp'=>$enable_email_otp,'firebase_otp_enabled'=>$firebase_otp]);

    }


    /**
     * Test Api
     * 
     * */
    public function testApi()
    {
        

        return get_directions(request()->pick_lat,request()->pick_lng,request()->drop_lat,request()->drop_lng);

        

    }
}
