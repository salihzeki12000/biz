<?php namespace App\Http\Controllers;

use App\Country;
use App\ItiItem;
use Auth;
use Illuminate\Contracts\Auth\Guard;
use Image;
use App\ItiDayPhoto;

use Stripe\Stripe;
use App\TravelStyle;
use App\City;
use App\Region;
use App\Itinerary;
use App\Transaction;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItineraryController extends Controller {

	protected $user;

	public function __construct(Guard $user)
		{
		parent::__construct();

		$this->user = Auth::user();

		view()->share('regions',Region::lists('region','id'));
		view()->share('items', ItiItem::all());

	}

	public function getItiFree(Itinerary $itinerary)
	{
		if ($itinerary->price == 0){
			//$user = Auth::user();
			$this->user->transactions()
				->attach($itinerary->id, [
					'currency'=>'usd',
					'purchase_price'=>$itinerary->price
				]);

			return redirect()->route('itinerary.show', $itinerary->slug)->withMessage('Itinerary added to your list');
		}
		else{
			return back()->withErrors('cannot add itinerary');
		}
	}

	public function purchaseConfirm(Itinerary $itinerary)
	{
		return view('payment.prePurchase')->with('itinerary', $itinerary);
	}

	public function purchase(Itinerary $itinerary, Request $request)
	{

		//$user = Auth::user();



		//check if user already has this in purchase list
		$purItineraries = $this->user->transactions()->where('itinerary_id',$itinerary->id)->get();
		if(!$purItineraries->isEmpty())
		{
			return back()->withErrors(' ERROR!!! You already have this itinerary in you purchased list');
		}


		Stripe::setApiKey(env('PLATFORM_SECRET_KEY'));

		// Create the charge on Stripe's servers - this will charge the user's card
		try {
			\Stripe\Charge::create(array(
				'amount' => $itinerary->price * 100,
				'currency' => 'usd',
				'source' => $request->stripeToken,
				'description' => $itinerary->title,
				'application_fee' =>  round($itinerary->price * 100 * 0.051) + 30// amount in cents, so use round(), 5.1% + 30c
			), array('stripe_account' => $itinerary->user->stripe_id));

		} catch(\Stripe\Error\Card $e) {
			// The card has been declined
			return redirect()->previous()->withErrors($e);
		}

		//remove purchased itinerary from favorite list if in user's favorite list
		// check if currently logged in user liked the article
		if($itinerary->liked())
		{
			$itinerary->unlike();
		}

		//store transaction record
		$user = Auth::user();
		$user->transactions()
				->attach($itinerary->id, [
					'currency'=>'usd',
					'purchase_price'=>$itinerary->price
				]);

		$transaction_id = Transaction::where('user_id', $user->id)->orderBy('created_at', 'desc')->first()->id;


		//send purchase confirmation email
		Mail::send('emails.purchase-confirmation', ['user'=>$user,'itinerary'=> $itinerary, 'transaction_id'=>$transaction_id], function($m) use($transaction_id,$itinerary, $user){
			$m->to($user->email, $user->name)->subject('TripPlanShop Order Confirmation: '.$itinerary->title);
		} );


		return view('payment.postPurchase', compact('itinerary', $itinerary, 'transaction_id', $transaction_id));

	}

	public function favorite(Itinerary $itinerary)
	{
		if(!$itinerary->liked())
		{
			$itinerary->like();
			return 'added';
		}
		else{
			$itinerary->unlike();
			return '';

		}
	}

	/**
	 * publish itinerary for sale
	 * @param $itinerary
	 * @return mixed
	 */
	public function publish(Itinerary $itinerary)
	{
		$itinerary->published = 1;
		$itinerary->save();

		return redirect()->route('user.getAllTripPlans', Auth::user())->withMessage($itinerary->title . ' is published now');
	}
	public function unpublish(Itinerary $itinerary)
	{
		$itinerary->published = 0;
		$itinerary->save();

		return redirect()->route('user.getAllTripPlans', Auth::user())->withMessage($itinerary->title . ' is NOT published now');
	}

	/**
	 * @param Request $request
	 * @return \Illuminate\View\View
	 */
	public function postSearch(Request $request)
	{
		//if mobile view search button used and has input? NO, then use regular search form inputs
		if ($request->has('country_mobile')) {

			$location = $request->location_mobile;
			$country = $request->country_mobile;
			$styles = $request->style_list_mobile;

		} else {
			$country = $request->country_name;
			$location = $request->location;
			$styles = $request->style_list;
			/*
			//in case uses click style tags fro iti card and send a single style tag search request
			if (is_array($request->style_list) || is_null($request->style_list)) {
				$styles = $request->input('style_list');
			} else {
				dd($request->input('style_list'));
				$styles[] = $request->input('style_list');
			}*/

		}

		$query = Itinerary::published(true)
			->select('itineraries.*', 'cities.city', 'countries.country', 'travelstyles.style')
			->from('itineraries')
			->join('city_itinerary', 'itineraries.id', '=', 'city_itinerary.itinerary_id')
			->join('cities', 'city_itinerary.city_id', '=', 'cities.id')
			->join('countries', 'cities.country_id', '=', 'countries.id')
			->join('itinerary_style', 'itineraries.id', '=', 'itinerary_style.itinerary_id')
			->join('travelstyles', 'itinerary_style.style_id', '=', 'travelstyles.id');

		if ($location != '') {
			$data = explode(',', $location);
			$city = trim($data[0]);
			$query->where('cities.city', '=', $city);
			//address input validation
			//address has 3 elements? or only city and country? or only city
			if (count($data) == 1) {
				//address only contains city
				$country_input = '';

			} elseif (isset($data[2])) {

				$country_input = trim($data[2]);
			} else {
				$country_input = trim($data[1]);
			}

			if ($country_input != '') {
				//input in "address search bar" only contains city name
				$query->where('countries.country', '=', $country_input);

				$city_country = Country::firstOrCreate(['country' => $country_input]);
				$city = trim($data[0]);
				City::firstOrCreate(['city' => $city, 'country_id' => $city_country->id]);

			}
		}

		if ($country != 'any' && !is_null($country)) {
			$query->where('countries.country', '=', $country);
		}


		if (!is_null($styles)) {



			if (count($styles) == 1) {
				$query->where('style', 'like', '%' . $styles[0] . '%');
			} else {
				$query->where(function ($qu) use ($styles) {
					foreach ($styles as $key => $style) {
						if ($key == 0) {
							$qu->where('style', 'like', '%' . $style . '%');
						} else {
							$qu->orWhere('style', 'like', '%' . $style .'%');
						}
					}
				});

			}
		}

		$query->groupBy('itineraries.id')
			->orderby('updated_at', 'desc');
		$itineraries = $query->get();


		//more recent trip plans
		$recentItineraries = $this->getRecentIti($itineraries);


		if (is_null($styles)) {
			$styles[] = $styles; //making $styles into an array
		}

		//dd($location);

		return view('itinerary.searchResults')
			->with('recentItineraries', $recentItineraries)
			->withItineraries($itineraries)
			->with('selected_styles', $styles)
			->with('selected_location', $location)
			->with('selected_country', $country);
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		//return redirect('home');

		//$i = Itinerary::find(1);
		//echo Url::route('itinerary.show',[$i]);
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{

		//must set up Stripe account first b4 selling
		if($this->user->stripe_active == 0)//Auth::user()->stripe_active == 0
		{
			return view('users.StripeConnect');
		}

		return view('itinerary.create');
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store(Request $request)
	{
		//validation
		$v = $this->ItitValidate($request);

		if($v->fails())
		{
			return redirect()->back()->withInput()->withErrors($v->errors());
		}

		$itinerary = new Itinerary([
			'user_id'=>Auth::user()->id,
			'title' => $request->input('title'),
			'slug' => str_slug($request->input('title')),
			'region_id' => $request->input('region_id'),
			'best_season' => $request->input('best_season'),
			'summary' => $request->input('summary'),
			'items_list' => implode(',',$request->items_list)
		]);

		//set price according to user's choice, radio button 'free'
		if($request->free == 1)
		{
			$itinerary['price'] = 0;
		}
		else{
			$itinerary['price'] = $request->price;
		}

		//slug
		$itinerary['slug'] = $this->makeSlug($request->input('title'));
		$itinerary->save();

		$tags = $this->createTags($request->input('styles_list'));
		$itinerary->styles()->sync($tags);

		$city_ids = $this->updateCities($request->input('cities_list'));
		$itinerary->cities()->sync($city_ids);


		return redirect()->route('itinerary.edit', $itinerary->slug)->withItinerary($itinerary);
	}

	public function createTags($tags)
	{
		foreach($tags as $key => $tag)
		{
			if(!is_numeric($tag))
			{
				$new_StyleTag = TravelStyle::firstOrCreate(['style'=>$tag]);
				$tags[$key] = "$new_StyleTag->id";
			}
		}

		return $tags;
	}
	public function updateCities($cities)
	{
		//load city_ids to sync the many to many relationship
		$city_ids = '';
		for($i=0;$i<count($cities);$i++)
		{
			if($cities[$i] != '') //'default' option has been removed!! will need to update this code here
			{
				$data = explode(',', $cities[$i]); //'0' city, '1', 'state', '2' 'country'

				//load country_id from country table or create new country
				$country = Country::firstOrCreate(['country' => trim(end($data))]);

				//city table update
				$city = City::firstOrCreate(['city'=>$data[0],  'country_id'=>$country->id]);
				$city_ids[] = $city->id;
			}
		}

		return $city_ids;
	}
	
	/*
	public function getItineraryExample(Itinerary $itinerary)
	{

		//$iti = Itinerary::find(env('DEMO_ITIT_ID'));

		return view('itinerary.itiView')
		->with('is_preview', '1')
		->withItinerary($itinerary);
	}*/
	
	/**
	 * Display the specified resource.
	 *
	 * @param  int  $itinerary
	 * @return Response
	 */
	public function show(Itinerary $itinerary)
	{

		//check if logged in user has purchased the itinerary for full view
		//$user = Auth::user();

			if(!Auth::check())
			{
				//not logged in, show itinerary as "preview"
				return view('itinerary.itiView')
					->with('is_preview', '1')
					->withItinerary($itinerary);
			}
			elseif($itinerary->transactions->find($this->user->id) != null || $itinerary->user_id == $this->user->id){
				//published and user has purchased, full view, ow user is the author
				return view('itinerary.itiView')
					->with('is_preview', '0')
					->withItinerary($itinerary);
			}
			elseif($itinerary->transactions->find($this->user->id) == null)
			{
				//notnot purchased, show itinerary as "preview"
				return view('itinerary.itiView')
					->with('is_preview', '1')
					->withItinerary($itinerary);
			}
			else{
				return back()->withErrors("Sorry but we are having problems completing your request!");
			}

	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit(Itinerary $itinerary)//
	{


		//switching off the itinerary for sale
		$itinerary->published = 0;
		$itinerary->save();

		return view('itinerary.edit')
			->withItinerary($itinerary);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id, Request $request
	 * @return Response
	 */
	public function update(Request $request, Itinerary $itinerary) //StoreItineraryEditRequest
	{
		$v = $this->ItitValidate($request);

		if($v->fails())
		{
			return redirect()->back()->withInput()->withErrors($v->errors());
		}

		$data = [
			'title' => $request->input('title'),
			'slug' => str_slug($request->input('title')),
			'region_id' => $request->input('region_id'),
			'best_season' => $request->input('best_season'),
			'summary' => $request->input('summary'),
			'items_list' => implode(',',$request->items_list)
		];
		if($request->free == 1)
		{
			$data['price'] = 0;
		}
		else{
			$data['price'] = $request->price;
		}

		//slug
		$data['slug'] = $this->makeSlug($request->input('title'), $itinerary->id);

		$itinerary->fill($data);
		$itinerary->save();

		//create tags on the fly
		$tags = $this->createTags($request->input('styles_list'));
		//many-many style tags attach
		$itinerary->styles()->sync($tags);

		//cities tags update
		$city_ids = $this->updateCities($request->input('cities_list'));
		$itinerary->cities()->sync($city_ids);

		return redirect()->route('itinerary.show', $itinerary->slug);
	}


	/** create unique slug for itineraries
	 *
	 * @param $title
	 * @param int $itiId
	 * @return string
	 */
	public function makeSlug($title, $itiId = 0)
	{
		$index = 1;
		$finalSlug = str_slug($title);

		while (Itinerary::where('slug', $finalSlug)->exists()) {

			$iti = Itinerary::where('slug', $finalSlug)->first();

			if($iti->id != $itiId){
				$finalSlug = str_slug($title) . '-' . $index++;
			}else{
				break;
			}

		}

		return $finalSlug;
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy(Itinerary $itinerary)
	{
		$viewer = count($itinerary->transactions()->get());

		//check if someone still owns this itinerary

		if( $viewer != 0 ){

			if($viewer == 1){
				return redirect()->back()->withErrors("Someone has purchased this itinerary within 6 months.
			You can only delete when the purchase expires.");
			}

			return redirect()->back()->withErrors($viewer . " people have purchased this itinerary within 6 months.
			You can delete when the purchases expire.");

		}else{
			$itinerary->delete();

			//delete photo folder
			$iti_dir = 'images/itineraries/' . $itinerary->getRouteKey();
			if(is_dir($iti_dir))
			{
				\File::deleteDirectory($iti_dir);
			}

			return redirect()->route('user.getInProgress', Auth::user())->withMessage($itinerary->title . ' has been deleted.');
		}

		//delete iti photo folders as well
	}

	public function getPopTripPlansPage()
	{

		$itineraries = 	Itinerary::published(true)->popular()->random()->get();

		//more recent trip plans
		$recentItineraries = $this->getRecentIti($itineraries);
		return view('itinerary.searchResults')
			->with('recentItineraries', $recentItineraries)
			//->with('pop_iti_search', 1)
			->withItineraries($itineraries)
			->with('selected_styles', []);

	}

	public function getSalesDetails(Itinerary $itinerary)
	{
		foreach($itinerary->transactions as $user){
			$user->transactions()->updateExistingPivot($itinerary->id, ['is_read' => 1]);
		}

		return view('users.iti_sales_details')->withItinerary($itinerary);
	}

	public function ItitValidate($request)
	{
		$v = Validator::make($request->all(), [
			'free' => 'required',

			'title' => 'required|min:10|max:100',
			'region_id' => 'required',
			//'top-place'=>'required',
			//'price' => 'required|numeric|min: '.env('ITI_MIN_PRICE').'|max:'.env('ITI_MAX_PRICE'),
			'best_season' => 'required',
			'styles_list' => 'required|max:5',
			'cities_list'=> 'required|max:'.env('ITI_MAX_CITY'),
			'items_list'=>	'required',
			'summary' => 'required'
		]);
		$v->sometimes('price', 'required|numeric|min: '.env("ITI_MIN_PRICE").'|max:'.env("ITI_MAX_PRICE"), function($input)
		{
			return $input->free == 0;
		});

		return $v;
	}

	public function getRecentIti($itineraries)
	{
		return Itinerary::whereNotIn('id', $itineraries->lists('id'))->published(true)->orderBy('created_at')->paginate(env('RECENT_ITI'));
	}

	public function storeCoverImage(Request $request)
	{


		$this->validate($request, [
			'iti_image' => 'required|mimes:jpg,jpeg,png'
		]);

		$itinerary = Itinerary::whereSlug($request->iti_slug)->firstOrFail();

		if($itinerary->image_path == '')
		{
			$iti_dir = 'images/itineraries' . '/' . $itinerary->getRouteKey();
			if(!is_dir($iti_dir))
			{
				if(!mkdir($iti_dir))
				{
					return "We are having technical difficulties. Please contact admin.";
				}
			}
		}else{

			\File::delete($itinerary->image_path);

		}

		$photo = ItiDayPhoto::makePhoto('1800', $request->file('iti_image'), $itinerary->getRouteKey(), 'cover-');

		$itinerary->image_path = $photo->photo_path;
		$itinerary->save();

	}
}
