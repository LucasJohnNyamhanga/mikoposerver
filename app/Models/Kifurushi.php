<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class Kifurushi
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $muda
 * @property int $number_of_offices
 * @property int $duration_in_days
 * @property float $price
 * @property int $sms
 * @property bool $is_active
 * @property bool $is_popular
 * @property string|null $offer
 * @property bool $special
 * @property string|null $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Kifurushi extends Model
{

    public function purchases(): HasMany
    {
        return $this->hasMany(KifurushiPurchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function verifiedAccounts(): HasMany|Kifurushi
    {
        return $this->hasMany(VerifiedAccount::class);
    }

    public function ofisiChanges(): HasMany
    {
        return $this->hasMany(OfisiChange::class);
    }


    protected $fillable = [
        'name',
        'description',
        'number_of_offices',
        'duration_in_days',
        'price',
        'sms',
        'offer',
        'is_popular',
        'special',
        'type',
        'muda',
        'is_active',
    ];


    protected $casts = [
        'is_active'   => 'boolean',
        'is_popular'  => 'boolean',
        'special'     => 'boolean',
        'price'       => 'decimal:2',
    ];
}
