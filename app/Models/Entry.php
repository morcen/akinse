<?php

namespace App\Models;

use App\Models\Enums\EntryTypesEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entry extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'category_id',
        'user_id',
        'type',
        'amount',
        'date',
        'description',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EntryTypesEnum::class,
            'amount' => 'decimal:2',
            'date' => 'date',
        ];
    }

    /**
     * Get the category that owns the entry.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the user that owns the entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(EntryPayment::class);
    }

    public function totalPaid(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->payments->sum('amount'),
        );
    }
}
