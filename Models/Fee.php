<?php

namespace App;

use Illuminate\Support\Arr;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Fee.
 *
 * @property int $id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property float $price_limit
 * @property int $perc
 * @property float $fee
 * @property int $company_id
 * @method static Builder|Fee whereId($value)
 * @method static Builder|Fee whereCreatedAt($value)
 * @method static Builder|Fee whereUpdatedAt($value)
 * @method static Builder|Fee wherePriceLimit($value)
 * @method static Builder|Fee wherePerc($value)
 * @method static Builder|Fee whereFee($value)
 * @method static Builder|Fee whereCompanyId($value)
 * @property-read \App\Fee $fees
 * @property-read \App\Company $company
 * @mixin \Eloquent
 */
class Fee extends Model
{
	/** @var string */
	protected $table = 'fees';
	
	/** @var bool */
	public $timestamps = true;
	
	/** @var array */
	protected $feeConfig;
	
	/**
	 * Fee constructor.
	 * @param array $attributes
	 */
	public function __construct(array $attributes = [])
	{
		parent::__construct($attributes);
		$this->feeConfig = config('settings.fees');
	}
	
	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function company()
	{
		return $this->belongsTo(\App\Company::class, 'company_id');
	}
	
	/**
	 * @param string $currency
	 * @param int $price
	 * @param int $companyId
	 * @return float
	 */
	public function getFee($currency, $price, $companyId)
	{
		$feePercent = $this->getCommissionConfig($currency, $price, $companyId);
		if (!is_array($feePercent)) {
			return $feePercent;
		}
		
		$fee = floatval(Arr::get($feePercent, 'fee', '0'));
		$percent = moneyTransform(floatval(Arr::get($feePercent, 'perc', '0')) * $price, false);
		
		return $fee + $percent;
	}
	
	/**
	 * @param int $companyId
	 * @return array
	 */
	public function getCustomFee($companyId)
	{
		$fee = self::whereCompanyId($companyId)->get();
		
		$customFee = [];
		foreach ($fee as $item) {
			$customFee[$item->price_limit] = ['fee' => $item->fee, 'perc' => $item->perc];
		}
		
		return $customFee;
	}
	
	/**
	 * @param int $companyId
	 * @return bool
	 */
	public function isHaveCustomFee($companyId)
	{
		return (bool)self::whereCompanyId($companyId)->count();
	}
	
	/**
	 * @param string $currency
	 * @param int $price
	 * @param int $companyId
	 * @return float|array
	 */
	public function getCommissionConfig($currency, $price, $companyId)
	{
		$fees = $this->getFeeList($companyId, $currency);
		if (!is_array($fees)) {
			return $fees;
		}
		
		$lastFeePercent = [];
		$price = floatval($price);
		foreach ($fees as $priceLimit => $feePercent) {
			$priceLimit = floatval($priceLimit);
			if ($priceLimit >= $price) {
				return $lastFeePercent;
			}
			$lastFeePercent = $feePercent;
		}
		
		return $lastFeePercent;
	}
	
	/**
	 * @param int $ticketItemId
	 * @return string
	 */
	public function getFeeByTicket($ticketItemId)
	{
		$orderItemId = Order_Item::whereTicketItemId($ticketItemId)->get()->first();
		
		return Order_Item::whereFeeTicketId($orderItemId->id)->first()->price;
	}
	
	/**
	 * @param int $companyId
	 * @param string $currency
	 * @return array
	 */
	public function getFeeList($companyId, $currency)
	{
		return $this->isHaveCustomFee($companyId) ? $this->getCustomFee($companyId) : $this->feeConfig[$currency];
	}
}
