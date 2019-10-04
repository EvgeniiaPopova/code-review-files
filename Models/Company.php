<?php

namespace App;

use DB;
use Auth;
use Stripe;
use Illuminate\Support\Arr;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Company.
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\User[] $users
 * @property-read \App\Country $country
 * @property-read \App\Settings $settings
 * @mixin \Eloquent
 * @property int $id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property string $name
 * @property string $pretty-url
 * @property string $stripe_id
 * @property int $country_id
 * @property int $reg_step
 * @property int $type
 * @method static Builder|Company whereId($value)
 * @method static Builder|Company whereCreatedAt($value)
 * @method static Builder|Company whereUpdatedAt($value)
 * @method static Builder|Company whereName($value)
 * @method static Builder|Company wherePrettyUrl($value)
 * @method static Builder|Company whereStripeId($value)
 * @method static Builder|Company whereCountryId($value)
 * @method static Builder|Company whereRegStep($value)
 * @method static Builder|Company whereType($value)
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Event[] $events
 * @property int $verification_status
 * @method static Builder|Company whereVerificationStatus($value)
 * @property string $phone
 * @method static \Illuminate\Database\Query\Builder|\App\Company wherePhone($value)
 * @property int $promo_id
 * @property-read \App\Promo $promos
 * @method static \Illuminate\Database\Query\Builder|\App\Company wherePromoId($value)
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Crm[] $crm
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Fee[] $fees
 * @property int $refunds_fee
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Company whereRefundsFee($value)
 * @property int $free_only
 * @property-read string $first_event_date
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Company whereFreeOnly($value)
 */
class Company extends Model
{
	/**#@+
	 * Companies Type constants
	 * @const int
	 */
	const TYPE_INDIVIDUAL = 1;
	const TYPE_COMPANY = 2;
	/**#@-*/
	
	/**
	 * @var string
	 */
	protected $table = 'companys';
	
	/**
	 * @var bool
	 */
	public $timestamps = true;
	
	/**
	 * @return bool
	 */
	public static function boot()
	{
		parent::boot();
		
		static::saving(function ($model) {
			$model->setPrettyUrl();
			
			return true; //False won't save
		});
	}
	
	/**
	 * Setting Name Attribute.
	 * @param string $value
	 */
	public function setNameAttribute($value)
	{
		if (isset($value)) {
			$this->attributes['pretty-url'] = uniquePrettyUrl($value, 'companys');
		}
		$this->attributes['name'] = $value;
	}
	
	/**
	 * Setting pretty URL.
	 */
	public function setPrettyUrl()
	{
		if (!isset($this->attributes['pretty-url'])) {
			$this->attributes['pretty-url'] = uniquePrettyUrl($this->attributes['name'], 'companys');
		}
	}
	
	/**
	 * Getting pretty URL.
	 * @return string
	 */
	public function getPrettyUrl()
	{
		return $this->attributes['pretty-url'];
	}
	
	/**
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 */
	public function users()
	{
		return $this->hasMany(\App\User::class, 'company_id');
	}
	
	/**
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 */
	public function events()
	{
		return $this->hasMany(\App\Event::class, 'company_id');
	}
	
	/**
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 */
	public function crm()
	{
		return $this->hasMany(\App\Crm::class, 'company_id');
	}
	
	/**
	 * @return \Illuminate\Database\Eloquent\Relations\HasOne
	 */
	public function settings()
	{
		return $this->hasOne(\App\Settings::class);
	}
	
	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function promos()
	{
		return $this->belongsTo(\App\Promo::class, 'promo_id');
	}
	
	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function country()
	{
		return $this->belongsTo(\App\Country::class, 'country_id');
	}
	
	/**
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 */
	public function fees()
	{
		return $this->hasMany(\App\Fee::class, 'company_id');
	}
	
	/**
	 * @param string $stripeId
	 * @param array $payouts
	 * @param null|string $startId
	 * @return array
	 */
	public static function payouts($stripeId, $payouts = [], $startId = null)
	{
		if (empty($stripeId)) {
			return [];
		}
		
		$stripeKey = config('services.stripe.secret');
		$payoutsList = stripePayouts($stripeKey, $stripeId, $startId);
		
		$payouts = array_merge($payouts, $payoutsList->data);
		
		if ($payoutsList->has_more) {
			$start = array_pop($payoutsList->data);
			$payouts = self::payouts($stripeId, $payouts, $start->id);
		}
		
		return $payouts;
	}
	
	/**
	 * @return array
	 */
	public static function adminAll()
	{
		return $data = DB::table('companys')
			->addSelect('companys.id as id', 'companys.name as company_name', 'companys.created_at as company_created_at')
			->addSelect(DB::raw('count(users.id) as users'))
			->addSelect('countrys.name as country_name')
			->leftjoin('countrys', 'countrys.id', '=', 'companys.country_id')
			->leftJoin('users', 'users.company_id', '=', 'companys.id')
			->groupBy('companys.id', 'companys.name', 'companys.created_at')
			->groupBy('countrys.name');
	}
	
	/**
	 * First date of all company's event.
	 *
	 * @return string
	 */
	public function getFirstEventDateAttribute()
	{
		$firstEventsDates = DB::table('showings as s')
			->addSelect(DB::raw('min(s.time) as date'))
			->rightJoin('events as e', 's.event_id', '=', 'e.id')
			->rightJoin('companys as c', 'e.company_id', '=', 'c.id')
			->where('c.id', '=', $this->id)
			->groupBy('c.id')
			->first();
		
		return $firstEventsDates->date;
	}
	
	/**
	 * @return array
	 */
	public function externalAccount()
	{
		$account = \Stripe::Account()->find($this->stripe_id);
		
		$externalAccounts = Arr::get($account['external_accounts'], 'data', []);
		
		return $externalAccounts;
	}
}
