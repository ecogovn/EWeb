<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Base\Filters\Admin\RequestFilter;
use App\Base\Libraries\QueryFilter\QueryFilterContract;
use App\Http\Controllers\Web\BaseController;
use App\Models\Request\Request as RequestRequest;
use Illuminate\Http\Request;
use App\Models\Master\PackageType;
use App\Transformers\Requests\PackagesTransformer;
use App\Models\User;
use App\Models\Admin\VehicleType;
use App\Base\Libraries\Access\Access;
use Illuminate\Support\Facades\Hash;
use App\Models\Request\RequestCycles;
use App\Models\Admin\ServiceLocation;
use Illuminate\Support\Facades\Auth;
use App\Helpers\Rides\FetchDriversFromFirebaseHelpers;
use Kreait\Firebase\Contract\Database;
use App\Models\Request\RequestMeta;
use App\Models\Admin\Driver;
use App\Jobs\Notifications\SendPushNotification;
use Log;

class DispatcherController extends BaseController
{
    use FetchDriversFromFirebaseHelpers;
    public function __construct(User $user,Database $database,RequestRequest $request_details)
    {
        $this->database = $database;
        $this->user = $user;
        $this->request_details = $request_details;
    }
    public function index()
    {
        $main_menu = 'dispatch_request';

        $sub_menu = null;

        $page = 'Dispatch Requests';

        return view('admin.dispatcher.requests', compact(['main_menu','sub_menu','page']));
    }

    public function dispatchView(){
        $main_menu = 'dispatch_request';

        $sub_menu = null;

        $page = 'Dispatch Requests';

        $default_lat = env('DEFAULT_LAT');
        $default_lng = env('DEFAULT_LNG');
        return view('admin.dispatcher.dispatch', compact(['main_menu','sub_menu','page', 'default_lat', 'default_lng']));
    }

    public function bookNow()
    {
         $main_menu = 'dispatch_request';

        $sub_menu = null;
         $default_lat = env('DEFAULT_LAT');
        $default_lng = env('DEFAULT_LNG');

        // $page = 'Dispatch Requests';
        return view('dispatch.new-ui.book-now')->with(compact('main_menu','sub_menu','default_lat', 'default_lng'));
    }

    /**
    *
    * create new request
    */
    public function createRequest(Request $request)
    {
        dd($request->all());
    }

    public function loginView(){
        return view('admin.dispatch-login');
    }
    public function detail_view(){
        $request = "W3sic3RhdHVzIjoxLCJwcm9jZXNzX3R5cGUiOiJjcmVhdGVfcmVxdWVzdCIsIm9yZGVyYnlfc3RhdHVzIjoxLCJkcmljdmVyX2RldGFpbHMiOm51bGwsImNyZWF0ZWRfYXQiOiIyMDI0LTAzLTIxIDA3OjI0OjU1In0seyJyYXRpbmciOiI1LjAwIiwic3RhdHVzIjoxLCJpc19hY2NlcHQiOjIsImRyaWN2ZXJfZGV0YWlscyI6eyJuYW1lIjoicmFua2l0aCB0ZXN0IiwibW9iaWxlIjoiOTU3ODc4Nzg3NDQiLCJpbWFnZSI6Imh0dHBzOlwvXC9heW9zZXJ2aWNlcy5jb21cL1wvYXNzZXRzXC9pbWFnZXNcL2RlZmF1bHQtcHJvZmlsZS1waWN0dXJlLnBuZyJ9LCJjcmVhdGVkX2F0IjoiMjAyNC0wMy0yMSAwNzoyNTowNyIsIm9yZGVyYnlfc3RhdHVzIjoxLCJwcm9jZXNzX3R5cGUiOiJhY2NlcHQifSx7InJhdGluZyI6IjUuMDAiLCJzdGF0dXMiOjQsInByb2Nlc3NfdHlwZSI6InRyaXBfYXJyaXZlZCIsIm9yZGVyYnlfc3RhdHVzIjoyLCJkcmljdmVyX2RldGFpbHMiOnsibmFtZSI6InJhbmtpdGggdGVzdCIsIm1vYmlsZSI6Ijk1Nzg3ODc4NzQ0IiwiaW1hZ2UiOiJodHRwczpcL1wvYXlvc2VydmljZXMuY29tXC9cL2Fzc2V0c1wvaW1hZ2VzXC9kZWZhdWx0LXByb2ZpbGUtcGljdHVyZS5wbmcifSwiY3JlYXRlZF9hdCI6IjIwMjQtMDMtMjEgMDc6MjU6MTEifSx7InJhdGluZyI6IjUuMDAiLCJzdGF0dXMiOjgsInByb2Nlc3NfdHlwZSI6ImRyaXZlcl9jYW5jZWxsZWQiLCJvcmRlcmJ5X3N0YXR1cyI6MywiZHJpY3Zlcl9kZXRhaWxzIjp7Im5hbWUiOiJyYW5raXRoIHRlc3QiLCJtb2JpbGUiOiI5NTc4Nzg3ODc0NCIsImltYWdlIjoiaHR0cHM6XC9cL2F5b3NlcnZpY2VzLmNvbVwvXC9hc3NldHNcL2ltYWdlc1wvZGVmYXVsdC1wcm9maWxlLXBpY3R1cmUucG5nIn0sImNyZWF0ZWRfYXQiOiIyMDI0LTAzLTIxIDA3OjI1OjE5In0seyJyYXRpbmciOiIwLjAwIiwic3RhdHVzIjoyLCJpc19hY2NlcHQiOjMsImRyaWN2ZXJfZGV0YWlscyI6eyJuYW1lIjoiS2F2aXlhcmFzYW4iLCJtb2JpbGUiOiI5ODQyNjE4NTg0IiwiaW1hZ2UiOiJodHRwczpcL1wvYXlvc2VydmljZXMuY29tXC9cL2Fzc2V0c1wvaW1hZ2VzXC9kZWZhdWx0LXByb2ZpbGUtcGljdHVyZS5wbmcifSwiY3JlYXRlZF9hdCI6IjIwMjQtMDMtMjEgMDc6MjU6MjMiLCJvcmRlcmJ5X3N0YXR1cyI6NCwicHJvY2Vzc190eXBlIjoiZGVjbGluZSJ9LHsicmF0aW5nIjoiNC43NCIsInN0YXR1cyI6MSwiaXNfYWNjZXB0IjoyLCJkcmljdmVyX2RldGFpbHMiOnsibmFtZSI6InJhbmppdGgiLCJtb2JpbGUiOiI5NTY2NzU0NDE4IiwiaW1hZ2UiOiJodHRwczpcL1wvYXlvc2VydmljZXMuczMuYW1hem9uYXdzLmNvbVwvdXBsb2Fkc1wvdXNlclwvcHJvZmlsZS1waWN0dXJlXC9JT2dzNVlRWUQzQ2lnb3NQQjdzcnB0MjNhMHEwUjBlTjFxd0VXdWlXLmpwZyJ9LCJjcmVhdGVkX2F0IjoiMjAyNC0wMy0yMSAwNzoyNTo1NSIsIm9yZGVyYnlfc3RhdHVzIjo1LCJwcm9jZXNzX3R5cGUiOiJhY2NlcHQifSx7InJhdGluZyI6IjQuNzQiLCJzdGF0dXMiOjIsImlzX2FjY2VwdCI6MywiZHJpY3Zlcl9kZXRhaWxzIjp7Im5hbWUiOiJyYW5qaXRoIiwibW9iaWxlIjoiOTU2Njc1NDQxOCIsImltYWdlIjoiaHR0cHM6XC9cL2F5b3NlcnZpY2VzLnMzLmFtYXpvbmF3cy5jb21cL3VwbG9hZHNcL3VzZXJcL3Byb2ZpbGUtcGljdHVyZVwvSU9nczVZUVlEM0NpZ29zUEI3c3JwdDIzYTBxMFIwZU4xcXdFV3VpVy5qcGcifSwiY3JlYXRlZF9hdCI6IjIwMjQtMDMtMjEgMDc6MjU6NTciLCJvcmRlcmJ5X3N0YXR1cyI6NiwicHJvY2Vzc190eXBlIjoiZGVjbGluZSJ9LHsicmF0aW5nIjoiNC43NCIsInN0YXR1cyI6NCwicHJvY2Vzc190eXBlIjoidHJpcF9hcnJpdmVkIiwib3JkZXJieV9zdGF0dXMiOjcsImRyaWN2ZXJfZGV0YWlscyI6eyJuYW1lIjoicmFuaml0aCIsIm1vYmlsZSI6Ijk1NjY3NTQ0MTgiLCJpbWFnZSI6Imh0dHBzOlwvXC9heW9zZXJ2aWNlcy5zMy5hbWF6b25hd3MuY29tXC91cGxvYWRzXC91c2VyXC9wcm9maWxlLXBpY3R1cmVcL0lPZ3M1WVFZRDNDaWdvc1BCN3NycHQyM2EwcTBSMGVOMXF3RVd1aVcuanBnIn0sImNyZWF0ZWRfYXQiOiIyMDI0LTAzLTIxIDA3OjI2OjA1In0seyJyYXRpbmciOiI0Ljc0Iiwic3RhdHVzIjo1LCJvcmRlcmJ5X3N0YXR1cyI6OCwicHJvY2Vzc190eXBlIjoidHJpcF9zdGFydCIsImRyaWN2ZXJfZGV0YWlscyI6eyJuYW1lIjoicmFuaml0aCIsIm1vYmlsZSI6Ijk1NjY3NTQ0MTgiLCJpbWFnZSI6Imh0dHBzOlwvXC9heW9zZXJ2aWNlcy5zMy5hbWF6b25hd3MuY29tXC91cGxvYWRzXC91c2VyXC9wcm9maWxlLXBpY3R1cmVcL0lPZ3M1WVFZRDNDaWdvc1BCN3NycHQyM2EwcTBSMGVOMXF3RVd1aVcuanBnIn0sImNyZWF0ZWRfYXQiOiIyMDI0LTAzLTIxIDA3OjI2OjEzIn0seyJyYXRpbmciOiI0Ljc0Iiwic3RhdHVzIjo2LCJwcm9jZXNzX3R5cGUiOiJ0cmlwX2NvbXBsZXRlZCIsIm9yZGVyYnlfc3RhdHVzIjo5LCJkcmljdmVyX2RldGFpbHMiOnsibmFtZSI6InJhbmppdGgiLCJtb2JpbGUiOiI5NTY2NzU0NDE4IiwiaW1hZ2UiOiJodHRwczpcL1wvYXlvc2VydmljZXMuczMuYW1hem9uYXdzLmNvbVwvdXBsb2Fkc1wvdXNlclwvcHJvZmlsZS1waWN0dXJlXC9JT2dzNVlRWUQzQ2lnb3NQQjdzcnB0MjNhMHEwUjBlTjFxd0VXdWlXLmpwZyJ9LCJjcmVhdGVkX2F0IjoiMjAyNC0wMy0yMSAwNzoyNjoxOCJ9XQ==";
        $data = json_decode(base64_decode($request));
        // print_r($data);
        // exit;
        $timezone = "Asia/Kolkata";
        return view('dispatch-new.detailed-view',compact('data','timezone'));
    }
    public function dashboard(){

        return view('dispatch.home');
    }

    public function dashboardOpen(){

        return view('dispatch.homeopen');
    }

    public function fetchBookingScreen($modal){
        return view("dispatch.$modal");
    }

    public function fetchRequestLists(QueryFilterContract $queryFilter){
        // $query = RequestRequest::where('if_dispatch', true)->where('dispatcher_id',auth()->user()->admin->id);
        $query = RequestRequest::query();

        $results = $queryFilter->builder($query)->customFilter(new RequestFilter)->paginate();

        return view('dispatch.request-list', compact('results'));
    }

    public function profile(){
        return view('dispatch.profile');
    }
    public function authenticate(Request $request){
        $dispatcher = User::where('email',$request->email)->first();
        if($dispatcher->hasRole('dispatcher')){
            if (Hash::check($request->password, $dispatcher->password)) {
                return $this->respondSuccess();
            } else {
                dd('no');
            }
        }
    }
    public function dashboard1(){
        $vehicle_types = VehicleType::where('active', 1)->get();
        return view('dispatch-new.home',compact('vehicle_types'));
    }
    public function requestView(Request $request){
        $type = 'all';
        $is_completed_count = RequestRequest::where('is_completed',1)->count();
        $user_cancelled_count = RequestRequest::where('is_cancelled',1)->where('cancel_method','1')->count();
        $driver_cancelled_count = RequestRequest::where('is_cancelled',1)->where('cancel_method','2')->count();
        $upcoming_count = RequestRequest::where('is_completed',0)->Where('is_cancelled',0)->where('is_driver_started',0)->where('is_later',1)->count();

        return view('dispatch-new.requests-list',compact('type','is_completed_count','user_cancelled_count','driver_cancelled_count','upcoming_count'));
    }
    public function fetch(Request $request){
        $type = $request->type;
        switch ($type) {
            case 'completed':
                $query = RequestRequest::where('is_completed',1);
                break;
            case 'cancelled':
                $query = RequestRequest::where('is_cancelled',1);
                break;
            case 'upcoming':
                $query = RequestRequest::where('is_completed',0)->Where('is_cancelled',0)->where('is_driver_started',0)->where('is_later',1);
                break;
            default:
                $query = RequestRequest::where('is_completed',1)->orWhere('is_cancelled',1)->orWhere(function($query){
                    $query->where('is_completed',0)->Where('is_cancelled',0)->where('is_driver_started',0)->where('is_later',1);
                });
                break;
        }
        $results = $query->orderBy('created_at','DESC')->paginate();
        return view('dispatch-new._requests',compact('results'));
    }

    public function requestDetailedView(RequestRequest $requestmodel){
        $item = $requestmodel;

        return view('dispatch.request_detail',compact('item'));
    }

    public function ongoingTrip(){
        $service_location = auth()->user()->admin->service_location_id;
        return view('dispatch-new.ongoing-trip',compact('service_location'));
    }

    public function detailView(RequestRequest $requestmodel){
        $item = $requestmodel;
        foreach ($item->requestRating as $key => $requestrating) {
            if($requestrating->user_rating){
                $item->user_rating = (int) $requestrating->rating;
            }
            if($requestrating->driver_rating){
                $item->driver_rating = (int) $requestrating->rating;
            }
        }
        // dd($item->driverDetail);
        $item->make = "-------";
        $item->model = "-------";
        if($item->driverDetail)
        {
            $item->make = $item->driverDetail->car_make_name ?? $item->driverDetail->custom_make;
            $item->model = $item->driverDetail->car_make_name ?? $item->driverDetail->custom_model;
        }
        $request_cycles_data = RequestCycles::where('request_id', $item->id)->first();
        $data = [];
        $service_location = ServiceLocation::find($item->service_location_id);
        $timezone = $service_location->timezone;
        if($request_cycles_data)
        {
            $data = json_decode(base64_decode($request_cycles_data->request_data));
        }
        // print_r($data);
        // exit;


        return view('dispatch-new.detailed-view',compact('item','data','timezone'));
    }
    public function book_ride(Request $request){
        $request =(object) $request->all();
        // $type = PackageType::where('transport_type','taxi')->orWhere('transport_type', 'both')->active()->get();

        return view('dispatch-new.book-ride',compact('request'));
    }
      /**
    * List Packages
    * @bodyParam pick_lat double required pikup lat of the user
    * @bodyParam pick_lng double required pikup lng of the user
    *
    */
    public function listPackages(Request $request){

        $request->validate([
            'pick_lat'  => 'required',
            'pick_lng'  => 'required',
        ]);

        // echo "sdfsdf";
        // exit;
        if($request->transport_type == "both")
        {
            $type1 = PackageType::Where('transport_type', 'both')->orWhere('transport_type', 'delivery')->orWhere('transport_type', 'taxi')->active();
        }
        else{
            $type1 = PackageType::where('transport_type',$request->transport_type)->orWhere('transport_type', 'both')->active();
        }
        if(isset($request->data_val))
        {
            $type = $type1->where('id',$request->data_val)->get();
        }
        else{
            $type = $type1->get();
        }

        $result = fractal($type, new PackagesTransformer);
        return $this->respondSuccess($result);

    }
    public function assigndriver(RequestRequest $request_model)
    {
        $item = $request_model;
        // dd($request_model,$request_model->userDetail);
        // dd("SDfdf");
        // $nearest_drivers =  $this->fetchDriversFromFirebase($request,'assign_manually',true);


        return view('dispatch-new.assign-driver',compact('item'));
    }
    public function assigmanual(RequestRequest $request_model,Request $request)
    {
        $request->validate([
            'driver_id'  => 'required'
        ]);
        $request_detail = $this->request_details->where('id',$request_model->id)->first();
            $selected_drivers["user_id"] = $request_detail->user_id;
            $selected_drivers["driver_id"] = $request->driver_id;
            $selected_drivers["active"] = 1;
            $selected_drivers["assign_method"] = 1;
            $selected_drivers["created_at"] = date('Y-m-d H:i:s');
            $selected_drivers["updated_at"] = date('Y-m-d H:i:s');
            $request_meta = new RequestMeta();
            $request_meta->request_id = $request_detail->id;
            $request_meta->user_id = $request_detail->user_id;
            $request_meta->driver_id = $request->driver_id;
            $request_meta->assign_method = 1;
            // $request_meta->save();
            $driver = Driver::find((int)($request->driver_id));
            $request_detail->requestMeta()->create($selected_drivers);
            $this->database->getReference('request-meta/'.$request_detail->id)->set(['driver_id'=>$driver->id,'request_id'=>$request_detail->id,'user_id'=>$request_detail->user_id,'active'=>1,'transport_type'=>"taxi",'updated_at'=> Database::SERVER_TIMESTAMP]);


        $notifable_driver = $driver->user;

        $title = trans('push_notifications.new_request_title',[],$notifable_driver->lang);
        $body = trans('push_notifications.new_request_body',[],$notifable_driver->lang);
        $push_data = ['title' => $title,'message' => $body,'push_type'=>'meta-request'];

        dispatch(new SendPushNotification($notifable_driver,$title,$body,$push_data));
        return response()->json(['status'=>true,'message'=>'Assigned Successfully']);
    }
    public function checkuserexist(Request $request)
    {
        $user_exist = $this->user->belongsToRole('user')
        ->where('mobile', $request->mobile)
        ->first();
        Log::info('------------------user_check------------------');
        Log::info($user_exist);
        if($user_exist)
        {
            return response()->json(['status'=>true,'message'=>'user exist','data'=>$user_exist]);
        }
        else{
            return response()->json(['status'=>false]);
        }
    }
    public function viewDispatchLogin()
    {    
        $recaptcha_enabled = get_settings('enable_recaptcha') ?? false; 
        
        return view('dispatch-new.login',compact('recaptcha_enabled'));
    }
}
