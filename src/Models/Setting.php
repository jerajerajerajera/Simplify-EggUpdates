<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property string $value
 */
final class Setting extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'simplify_egg_update_settings';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];
}
