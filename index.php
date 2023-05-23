<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Cities;
use App\Models\Front\Properties_Bidder;
use App\Models\PropertyView;
use App\Models\States;
use App\PlaceType;
use App\Property;
use App\Propertyrule;
use App\Testimonial; //
use App\User;
use App\Utility;
use Auth;
use DB;
use Illuminate\Http\Request;
use Redirect;
use Session;

class HomepageController extends Controller
{

    use \App\Traits\CmsTraits;
	
	
      // ====  Index method show property near by location === //
    public function index()
    {
        $states = States::all();
        $st_ar = array();
        $city = '';

        foreach ($states as $st) {
            $st_ar[] = trim($st->name);
        }

        $city = '';
        $ip = $_SERVER['REMOTE_ADDR'];
        $details = json_decode(file_get_contents("http://ipinfo.io/{$ip}/json"));
       

        if (isset($details->city)) {

            $address = $details->city . ' , ' . $details->region;
            $city = $details->city;

            $address = $details->city . ' , ' . $details->region;
            $city = $details->city;
        } else {
            $address = '';
        }

        $st_ar = json_encode($st_ar);

        $cms = $this->getCmsContent();
        $properties = Property::getAllProprties();
        $landlords = User::latest()->where('role_id', 2)->get();
        $testimonials = Testimonial::getAllTestimonial();

        return view('frontend.home.index', compact('cms', 'properties', 'landlords', 'testimonials', 'st_ar', 'address', 'city'));
    }

   
    // ==== Show Single Property Details  By Slug ===
    public function singleProperty($slug)
    {
        $property = Property::getPropertyBySlug($slug); 
        if (empty($property)) {
            Session::flash('error', 'Property dose not exist!');
            return Redirect::back();
        } else {
            $id = $property->id;
        }
        $PropertyHistry = [];
        if ($property->is_leased == 1) {
            $PropertyHistry = getPropertyBiddersByPropertyId($id);
            if (isset($PropertyHistry) && !empty($PropertyHistry)) {
                foreach ($PropertyHistry as $key => $val) {
                    $BidderUser = User::where('id', $val->user_id)->first();
                    $PropertyHistry[$key]->UserDetails = $BidderUser;
                }
            }
        }

        if (isset(Auth::user()->id)) {
            $user_id = Auth::user()->id;
            if (isset($property) && !empty($property) && $user_id != $property->agent_id) {
                $data['property_id'] = $id;
                $data['user_id'] = $user_id;
                PropertyView::addWiew($id, $user_id, $data);
            }
        }

        $category = $property->type;
        $similar = Property::getAllProprtiesByType($category, $id);

        // Similer Property For Grap Chart Start
        $similerLeaseProBidder = [];
        $similerLeaseProBidderNear = [];
        foreach ($similar as $key => $val) {
            if ($val->is_leased == 1) {
                $similerLeaseProBidder[] = getPropertyBiddersByPropertyId($val->id);

            }
        }
        $similerLeaseProBidderNear = findNearestPropertise($property->location_latitude, $property->location_longitude, $category, 1);
        // Similer Property For Grap Chart End

        return view('frontend.home.single', compact('property', 'similar', 'PropertyHistry', 'similerLeaseProBidder', 'similerLeaseProBidderNear'));
    }

    // ===============================================
    // ==== Search Properties By Property Name =======
    public function searchProperty(Request $request)
    {
        $request->validate([
            'search' => 'required',
        ]);

        $search = $request->search;
        $search = Property::orderby('id', 'DESC')->select('*')->where('title', 'like', '%' . $search . '%')->orWhere('address', 'like', '%' . $search . '%')->orWhere('area', 'like', '%' . $search . '%')->orWhere('city', 'like', '%' . $search . '%')->limit(5)->get();

        return view('frontend.home.search', compact('search'));
    }

    // ===============================================
    // ==== Autocomplete By user location ============
    public function getAutocomplete(Request $request)
    {
        $post = $request->all();
        $search = "";
        $autocomplate = "";

        $orderby = "asc";
        $current = date("Y-m-d h:i");

        $orderby = (isset($post['order']) && !empty($post['order'])) ? $post['order'] : "asc";

        $ip = $_SERVER['REMOTE_ADDR'];

        $address = 'Noida';

        $ip = $_SERVER['REMOTE_ADDR'];
        $details = json_decode(file_get_contents("http://ipinfo.io/{$ip}/json"));

        if (isset($details->city)) {
            $address = $details->city . ' , ' . $details->region;
        }

        if (isset($post['query']) && !empty($post['query'])) {

            $search = $post['query'];

            $checState = States::where(['name' => $search])->first();

            if ($checState) {

                $autocomplate = Property::select("properties.*", "properties.id as id")->where(['properties.city' => $checState->id])->where(DB::raw("(DATE_FORMAT(bib_exp,'%Y-%m-%d %h:%i'))"), '>=', $current)->where(DB::raw("(DATE_FORMAT(availability_date,'%Y-%m-%d %h:%i'))"), '<=', $current)->where('is_leased', 0)->limit(5)->orderby('min_price', $orderby)->get();

            } else { 
                
                $autocomplate = Property::select("properties.*", "properties.id as id")->orWhere('properties.address', 'like', '%' . $search . '%')->orWhere('properties.area', 'like', '%' . $search . '%')->orWhere('properties.city', 'like', '%' . $search . '%')->where(DB::raw("(DATE_FORMAT(bib_exp,'%Y-%m-%d %h:%i'))"), '>=', $current)->where(DB::raw("(DATE_FORMAT(availability_date,'%Y-%m-%d %h:%i'))"), '<=', $current)->where('is_leased', 0)->orderby('max_price', $orderby)->get();

            }

            if ($search == 'current') {
                $getLatLong = $this->getLatLong();

                $latitude = $getLatLong['latitude'];
                $longitude = $getLatLong['longitude'];
                $distance = 10;

                $autocomplate = Property::select("properties.*", "properties.id as id")->where(DB::raw("(DATE_FORMAT(bib_exp,'%Y-%m-%d %h:%i'))"), '>=', $current)->where(DB::raw("(DATE_FORMAT(availability_date,'%Y-%m-%d %h:%i'))"), '<=', $current)->where('is_leased', 0)->whereRaw("(1.609344 * 3956 * acos( cos( radians('$latitude') ) * cos( radians(location_latitude) ) * cos( radians(location_longitude) - radians('$longitude') ) + sin( radians('$latitude') ) * sin( radians(location_latitude) ) ) ) <= $distance")->orderby('min_price', $orderby)->get();

            }

            // filter result
            if ((isset($post['bed']) && !empty($post['bed'])) || (isset($post['amenities']) && !empty($post['amenities'])) || (isset($post['prop_type']) && !empty($post['prop_type'])) || (isset($post['prop_rule']) && !empty($post['prop_rule']))) {

               
                $where = "properties.is_leased=0";

                if (!isset($checState->id) && !empty($checState->id)) {

                    $where .= " AND properties.city=" . $checState->id;
                }

                if (isset($post['bed']) && !empty($post['bed'])) {
                    $where .= ' AND properties.bedroom=' . $post['bed'];
                } elseif (isset($post['amenities']) && !empty($post['amenities'])) {
                    $where .= ' AND property_utility.utility_id=' . $post['amenities'];
                } elseif (isset($post['prop_type']) && !empty($post['prop_type'])) {
                    $where .= ' AND properties.type="' . $post['prop_type'] . '"';
                } elseif (isset($post['prop_rule'])) {
                    $where .= ' AND property_rules.rule_id="' . $post['prop_rule'] . '"';
                } 

                $autocomplate = Property::select("properties.*", "properties.id as id")->join('property_utility', 'property_utility.property_id', '=', 'properties.id')->join('property_rules', 'property_rules.property_id', '=', 'properties.id')->where(DB::raw("(DATE_FORMAT(bib_exp,'%Y-%m-%d %h:%i'))"), '>=', $current)->where(DB::raw("(DATE_FORMAT(availability_date,'%Y-%m-%d %h:%i'))"), '<=', $current)->where('is_leased', 0)->where('properties.city', 'like', '%' . $search . '%')->whereRaw($where)->orderby('min_price', $orderby)->groupBy('properties.id')->get();

                // End filter

            }

            $mapArr = array();
            $st_ar = ""; 
            foreach ($autocomplate as $k => $mapVal) {

                $content = "";
                $new = array();
                $content .= "<div class='map-modal modal-sm p-2'>" . "<img class='border-radius-6' src='" . getPropertyImage($mapVal->id) . "' />" .
                "<div id='bodyContent' class='pl-2 pr-2'>" .
                "<p class='font-21 font-weight-bold mb-1'>" .
                "<strong class='font-14 theme-color-one'>$" . $mapVal->min_price . " / </strong>" .
                "<strong class='font-14 status-color-five'>$" . $mapVal->max_price . "</strong>";

                if (isset(Auth::user()->id) && Auth::user()->role_id == 3) {
                    $checkwishlist = checkWishlist($mapVal->id, Auth::user()->id);
                    if (isset($checkwishlist) && !empty($checkwishlist) && $checkwishlist->is_deleted == 0) {
                        $content .= "<a href='" . url('user/add/wishlist', $mapVal->id) . "'><img class='check-icon ml-2' src='" . asset('img/icons/like-vector.png') . "' style='width:15px;'> </a>";
                    } else {
                        $content .= "<a href='" . url('user/add/wishlist', $mapVal->id) . "'> <img  class='check-icon ml-2' src='" . url('img/icons/wishlist-yellow-icon.png') . "' style='width:15px;'></a>";
                    }

                } else {
                    $content .= "<a href='" . url('user/add/wishlist', $mapVal->id) . "'> <img  class='check-icon ml-2' src='" . url('img/icons/wishlist-yellow-icon.png') . "' style='width:15px;'></a>";
                }

                $content .= "<img class='ml-2 check-icon' src='" . asset('img/icons/touch.png') . "' style='width:15px;'>" .
                "</p>" .
                "<p class='font-10 font-11 font-weight-1000 mb-2 dark-color-second'><img class='mr-1' style='width: 15px;' src='" . asset('img/icons/location-vector.png') . "'> {$mapVal->address}, {$mapVal->city} {$mapVal->postal_code}</p></div>";

                $new[] = $content;
                $new[] = (Float) $mapVal->location_latitude;
                $new[] = (Float) $mapVal->location_longitude;
                $new[] = $k + 1;
                $mapArr[] = $new;
            }
            // fetch utilities array
            $utility = Utility::where(['status' => 1, 'deleted_at' => 0])->get();
            foreach ($utility as $k => $util) {
                $amenties[] = $util;
            }
            $utility = array_chunk($amenties, 3);

            // fetch property type list
            $prop_type = PlaceType::where(['status' => 1, 'deleted_at' => 0])->get();
            foreach ($prop_type as $k => $prop) {
                $property_type[] = $prop;
            }
            $pro_type = array_chunk($property_type, 3);

            // fetch property rules list
            $prop_rules = Propertyrule::where(['status' => 1, 'is_deleted' => 0])->get();
            foreach ($prop_rules as $k => $rules) {
                $pro_rules[] = $rules;
            }
            $property_rule = array_chunk($pro_rules, 3);

            $states = Cities::all();
            $st_ar = array();
            foreach ($states as $st) {
                $st_ar[] = trim($st->name);
            }

        }

        if (isset($st_ar) && !empty($st_ar)) {

            $st_ar = json_encode($st_ar);
        }

        return view('frontend.properties.list', compact('autocomplate', 'mapArr', 'utility', 'pro_type', 'property_rule', 'search', 'st_ar', 'address'));

    }

    // ===============================================
    // ====== New autosearch By Property Namde =======
    public function getPropertyAutocomplete(Request $request)
    {

        $post = $request->all();

        $search = "";

        $orderby = "asc";
        $current = date("Y-m-d h:i");

        $orderby = (isset($post['order']) && !empty($post['order'])) ? $post['order'] : "asc";

        $ip = $_SERVER['REMOTE_ADDR'];

        $address = 'Noida';

        $ip = $_SERVER['REMOTE_ADDR'];
        $details = json_decode(file_get_contents("http://ipinfo.io/{$ip}/json"));

        if (isset($details->city)) {
            $address = $details->city . ' , ' . $details->region;
        }

        if (isset($post['query']) && !empty($post['query'])) {

            $search = $post['query'];

            $checState = States::where(['name' => $search])->first();

            if ($checState) {

                $autocomplate = Property::select("properties.*", "properties.id as id")->where(['properties.city' => $checState->id])->where(DB::raw("(DATE_FORMAT(bib_exp,'%Y-%m-%d %h:%i'))"), '>=', $current)->where(DB::raw("(DATE_FORMAT(availability_date,'%Y-%m-%d %h:%i'))"), '<=', $current)->where('is_leased', 0)->limit(5)->orderby('min_price', $orderby)->get();

            } else {

                $autocomplate = Property::select("properties.*", "properties.id as id")->orWhere('properties.address', 'like', '%' . $search . '%')->orWhere('properties.area', 'like', '%' . $search . '%')->orWhere('properties.city', 'like', '%' . $search . '%')->where(DB::raw("(DATE_FORMAT(bib_exp,'%Y-%m-%d %h:%i'))"), '>=', $current)->where(DB::raw("(DATE_FORMAT(availability_date,'%Y-%m-%d %h:%i'))"), '<=', $current)->where('is_leased', 0)->orderby('min_price', $orderby)->get();

            }

            if ($search == 'current') {

                $getLatLong = $this->getLatLong();
                $latitude = $getLatLong['latitude'];
                $longitude = $getLatLong['longitude'];
                $distance = 10;
                $autocomplate = Property::select("properties.*", "properties.id as id")->where(DB::raw("(DATE_FORMAT(bib_exp,'%Y-%m-%d %h:%i'))"), '>=', $current)->where('is_leased', 0)->where(DB::raw("(DATE_FORMAT(availability_date,'%Y-%m-%d %h:%i'))"), '<=', $current)->whereRaw("(1.609344 * 3956 * acos( cos( radians('$latitude') ) * cos( radians(location_latitude) ) * cos( radians(location_longitude) - radians('$longitude') ) + sin( radians('$latitude') ) * sin( radians(location_latitude) ) ) ) <= $distance")->orderby('min_price', $orderby)->get();

            }

            // filter result
            if ((isset($post['bedroom']) && !empty($post['bedroom'])) ||
                (isset($post['bathroom']) && !empty($post['bathroom'])) ||
                (isset($post['amenities']) && !empty($post['amenities'])) || (isset($post['prop_type']) && !empty($post['prop_type'])) || (isset($post['prop_rule']) && !empty($post['prop_rule']) || (isset($post['max_price']) && !empty($post['max_price'])))) {

           
                $where = "properties.is_leased=0";
                if (!isset($checState->id) && !empty($checState->id)) {

                    $where .= " AND properties.city=" . $checState->id;
                }

                if (isset($post['bedroom']) && !empty($post['bedroom'])) {

                    $where .= ' AND properties.bedroom=' . $post['bedroom'];

                }

                if (isset($post['occupancy']) && !empty($post['occupancy'])) {
                    $where .= ' AND properties .max_occupancy<=' . $post['occupancy'];
                }

                if (isset($post['bathroom']) && !empty($post['bathroom'])) {

                    $where .= ' AND properties.bathroom=' . $post['bathroom'];

                }

                if (isset($post['amenities']) && !empty($post['amenities'])) {
                    $where .= ' AND property_utility.utility_id=' . $post['amenities'];
                }

                //max_occupancy

                if (isset($post['prop_type']) && !empty($post['prop_type'])) {
                    $prop_type = "";
                    switch ($post['prop_type']) {
                        case "Apartment":
                            $prop_type = 1;
                            break;
                        case "Condominium":
                            $prop_type = 2;
                            break;
                        case "House":
                            $prop_type = 3;
                            break;
                        case "Basement-Apartment":
                            $prop_type = 4;
                            break;

                    }
 
                    $where .= ' AND properties.type="' . $prop_type . '"';

                }

                
              
                if (isset($post['occupancy']) && !empty($post['occupancy'])) {
                    $where .= ' AND properties .max_occupancy<=' . $post['occupancy'];
                }

                
                $query = Property::select("properties.*", "properties.id as id")->join('property_utility', 'property_utility.property_id', '=', 'properties.id')->where(DB::raw("(DATE_FORMAT(bib_exp,'%Y-%m-%d %h:%i'))"), '>=', $current)->where(DB::raw("(DATE_FORMAT(availability_date,'%Y-%m-%d %h:%i'))"), '<=', $current)->where('is_leased', 0)->where('properties.city', 'like', '%' . $search . '%')->whereRaw($where);

                if ($request->has('max_price') && $request->has('min_price')) {

                    // Code for min and max price
                    $min_price = 0;
                    $max_price = 0;
                    if ($request->has('min_price')) {
                        $min_price = $request->get('min_price');
                    }

                    if ($request->has('max_price')) {
                        $max_price = $request->get('max_price');
                    }

                    if (!is_null($max_price)) {
                        $query->where('max_price', '>=', $max_price);

                    } elseif (!is_null($min_price)) {
                        $query->where('min_price', '<=', $min_price);
                    }

                }

                $autocomplate = $query->orderby('min_price', $orderby)->groupBy('properties.id')->get();

                // End filter

            }
            

            $mapArr = array();
            $st_ar = "";

            foreach ($autocomplate as $k => $mapVal) {

                $new = array();
                
                $content = "";

                $content .= "<div class='map-modal modal-sm'>" .
                '<div class="map_location_img"><img src="' . getPropertyImage($mapVal->id) . '" class="img-responsive" /></div>' .
                '<div id="bodyContent" class="pl-2 pr-2">' .
                "<p class='font-21 font-weight-bold mb-1'>" .
                "<strong class='font-14 theme-color-one'>$" . $mapVal->min_price . " / </strong>" .
                "<strong class='font-14 status-color-five'>$" . $mapVal->max_price . "</strong>";

                if (isset(Auth::user()->id) && Auth::user()->role_id == 3) {
                    $checkwishlist = checkWishlist($mapVal->id, Auth::user()->id);
                    if (isset($checkwishlist) && !empty($checkwishlist) && $checkwishlist->is_deleted == 0) {
                        $content .= "<a href='" . url('user/add/wishlist', $mapVal->id) . "'><img class='check-icon ml-2' src='" . asset('img/icons/like-vector.png') . "' style='width:15px;'> </a>";
                    } else {
                        $content .= "<a href='" . url('user/add/wishlist', $mapVal->id) . "'> <img  class='check-icon ml-2' src='" . url('img/icons/wishlist-yellow-icon.png') . "' style='width:15px;'></a>";
                    }

                } else {
                    $content .= "<a href='" . url('user/add/wishlist', $mapVal->id) . "'> <img  class='check-icon ml-2' src='" . url('img/icons/wishlist-yellow-icon.png') . "' style='width:15px;'></a>";
                }

                $content .= "<img class='check-icon' src='../img/icons/green-check-icon.png' >" .
                "</p>" .
                '<p class="mb-1 font-10"><i class="fa fa-map-marker mr-2" aria-hidden="true"></i>' . $mapVal->address . '</p>' .
                '<p class="mb-1 font-10 font-weight-bold">' . $mapVal->bedroom . ' Bed | ' . $mapVal->bathroom . ' Bath </p>' .
                    '</div>';

                $new[] = $content;
                $new[] = (Float) $mapVal->location_latitude;
                $new[] = (Float) $mapVal->location_longitude;
                $new[] = $k + 1;
                $mapArr[] = $new;
            }
            // fetch utilities array
            $utility = Utility::where(['status' => 1, 'deleted_at' => 0])->get();
            foreach ($utility as $k => $util) {
                $amenties[] = $util;
            }
            $utility = array_chunk($amenties, 3);

            // fetch property type list
            $prop_type = PlaceType::where(['status' => 1, 'deleted_at' => 0])->get();
            foreach ($prop_type as $k => $prop) {
                $property_type[] = $prop;
            }
            $pro_type = array_chunk($property_type, 3);

            // fetch property rules list
            $prop_rules = Propertyrule::where(['status' => 1, 'is_deleted' => 0])->get();
            foreach ($prop_rules as $k => $rules) {
                $pro_rules[] = $rules;
            }
            $property_rule = array_chunk($pro_rules, 3);

            $states = Cities::all();
            $st_ar = array();
            foreach ($states as $st) {
                $st_ar[] = trim($st->name);
            }

        }

        if (isset($st_ar) && !empty($st_ar)) {

            $st_ar = json_encode($st_ar);
        }

        return view('frontend.properties.list', compact('autocomplate', 'mapArr', 'utility', 'pro_type', 'property_rule', 'search', 'st_ar', 'address'));
    }

    // ================================================
    // ======= New autoserach By Lat Long Address =====
    public function getLatLong()
    {

        //Send request and receive json data by address

        $ip = $_SERVER['REMOTE_ADDR'];
        $details = json_decode(file_get_contents("http://ipinfo.io/{$ip}/json"));

        $address = $details->city . ' , ' . $details->region;

        //   $geocodeFromAddr = file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?sensor=false');

        $geocodeFromAddr = file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=AIzaSyAw6ieXdPUZmaJrOZwBwO3-MEwrOuVNngQ');

        $output = json_decode($geocodeFromAddr);

        //Get latitude and longitute from json data

        $data['latitude'] = $output->results[0]->geometry->location->lat;

        $data['longitude'] = $output->results[0]->geometry->location->lng;

        $data['address'] = $address;

        return $data;

    }

    // =================================================
    // ======= Get State List By Country ===============
    public function getStateByCountry(Request $request)
    {
        $country = $request->country;
        $state = new States();
        $state = $state->getStates($country);
        return response()->json($state);
    }

    // ================================================
    // ============ Load Checkout View ================
    public function checkout()
    {

        return view('frontend.checkout');
    }

    // ================================================
    // ============ Load Payment View =================
    public function payment_status()
    {

        return view("frontend.payment-status");
    }

    // ================================================
    // =============== Logout Function ================
    public function logout()
    {
        Auth::logout();
        Session::flush();
        return redirect('/login');
    }

    // ================================================
    // =========== Get Similar Properties =============
    public function getSimilerPro(Request $request)
    {
        $data = $request->all();
        $similerProperty = Property::where('is_leased', 1)->get();
        $similerLeaseProBidder = [];
        foreach ($similerProperty as $key => $val) {
            if ($val->is_leased == 1) {
                $similerLeaseProBidder = Properties_Bidder::where('property_id', $val->id)->whereYear('updated_at', '=', $data['year'])->get();
            }
        }

        return $similerLeaseProBidder;
    }

}
