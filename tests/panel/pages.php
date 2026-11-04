<?php

// Opens the pages of Egg Updates and Pelican's Eggs page as a root admin, whichever version is installed: for the step
// in checks.yml that puts the previous release back over the new tables. Exit 1 when one does not open.
// Run from the panel folder: php <checkout>/tests/panel/pages.php

use App\Filament\Admin\Resources\Eggs\EggResource;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Filament::setCurrentPanel(Filament::getPanel('admin'));

$admin = User::factory()->create();
$admin->syncRoles(Role::getRootAdmin());
$client = new class($app)
{
    use InteractsWithAuthentication, InteractsWithSession, MakesHttpRequests;

    public function __construct(public $app) {}
};
$failed = false;
foreach ([EggResource::getUrl('index', panel: 'admin'), url('/admin/egg-updates')] as $url) {
    $response = $client->actingAs($admin)->get($url);
    $status = $response->getStatusCode();
    echo ($status === 200 ? 'PASS ' : 'FAIL ').$url.' '.$status.($response->exception ? ': '.$response->exception->getMessage() : '')."\n";
    $failed = $failed || $status !== 200;
}
$admin->delete();
exit($failed ? 1 : 0);
