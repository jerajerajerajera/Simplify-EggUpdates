<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A test of an egg update: waiting (queued), or running on a temporary server (installing, starting).
 *
 * @property int $id
 * @property int $egg_id
 * @property string $stage
 * @property int|null $copy_egg_id the imported copy of the new definition the test server runs on
 * @property int|null $server_id
 * @property int|null $allocation_id
 * @property string|null $content the tested definition, installed as is when the test passes
 * @property string|null $format
 * @property string|null $hash
 * @property \Illuminate\Support\Carbon|null $stage_at
 */
final class EggTest extends Model
{
    public const QUEUED = 'queued';

    public const INSTALLING = 'installing';

    public const STARTING = 'starting';

    protected $table = 'simplify_egg_update_tests';

    protected $fillable = ['egg_id', 'stage', 'copy_egg_id', 'server_id', 'allocation_id', 'content', 'format', 'hash', 'stage_at'];

    protected function casts(): array
    {
        return ['stage_at' => 'datetime'];
    }
}
