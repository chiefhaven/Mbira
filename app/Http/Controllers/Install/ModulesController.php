<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Module;
use ZipArchive;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class ModulesController extends Controller
{
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ModuleUtil  $moduleUtil
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        //Get list of all modules.
        $modules = Module::toCollection()->toArray();
        //print_r($modules);exit;

        foreach ($modules as $module => $details) {
            $modules[$module]['is_installed'] = $this->moduleUtil->isModuleInstalled($details['name']) ? true : false;

            //Get version information.
            if ($modules[$module]['is_installed']) {
                $modules[$module]['version'] = $this->moduleUtil->getModuleVersionInfo($details['name']);
            }

            //Install Link.
            try {
                $modules[$module]['install_link'] = action('\Modules\\'.$details['name'].'\Http\Controllers\InstallController@index');
            } catch (\Exception $e) {
                $modules[$module]['install_link'] = '#';
            }

            //Update Link.
            try {
                $modules[$module]['update_link'] = action('\Modules\\'.$details['name'].'\Http\Controllers\InstallController@update');
            } catch (\Exception $e) {
                $modules[$module]['update_link'] = '#';
            }

            //Uninstall Link.
            try {
                $modules[$module]['uninstall_link'] = action('\Modules\\'.$details['name'].'\Http\Controllers\InstallController@uninstall');
            } catch (\Exception $e) {
                $modules[$module]['uninstall_link'] = '#';
            }
        }

        $is_demo = (config('app.env') == 'demo');
        $mods = $this->__available_modules();
        
        return view('install.modules.index')
            ->with(compact('modules', 'is_demo', 'mods'));

        //Option to uninstall

        //Option to activate/deactivate

        //Upload module.
    }

    public function regenerate()
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            Artisan::call('module:publish');
            Artisan::call('passport:install --force');
            // Artisan::call('scribe:generate');

            $output = ['success' => 1,
                'msg' => __('lang_v1.success'),
            ];
        } catch (Exception $e) {
            $output = ['success' => 1,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect()->back()->with('status', $output);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Activate/Deaactivate the specified module.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $module_name)
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            $module = Module::find($module_name);

            //php artisan module:disable Blog
            if ($request->action_type == 'activate') {
                $module->enable();
            } elseif ($request->action_type == 'deactivate') {
                $module->disable();
            }
            // Publish assets for this specific module after status change
            Artisan::call('module:publish', ['module' => $module_name, '--force' => true]);

            // Clear module assets cache when module is activated/deactivated
            Cache::forget('module_assets');

            $output = ['success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            $output = ['success' => false,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    /**
     * Deletes the module.
     *
     * @param  string  $module_name
     * @return \Illuminate\Http\Response
     */
    public function destroy($module_name)
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            $module = Module::find($module_name);
            // $module->delete();

            $path = $module->getPath();

            // Clear module assets cache when module is deleted
            Cache::forget('module_assets');

            die("To delete the module delete this folder <br/>" . $path . '<br/> Go back after deleting');

            $output = ['success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            $output = ['success' => false,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    /**
     * Upload the module.
     */
    public function uploadModule(Request $request)
    {
        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            $request->validate([
                'module' => 'required|file|mimes:zip|max:10240', // 10MB max
            ]);

            //get zipped file
            $module = $request->file('module');
            $module_name = Str::slug(str_replace('.zip', '', $module->getClientOriginalName()));

            //check if 'Modules' folder exist or not, if not exist create
            $path = '../Modules';
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }

            //extract the zipped file in given path
            $zip = new ZipArchive();
            if ($zip->open($module) === true) {
                $zip->extractTo($path.'/');
                $zip->close();

                // Check for required files after extraction
                $module_dir = $path . '/' . $module_name;
                $data_controller_path = $module_dir . '/Http/Controllers/DataController.php';
                if (!(file_exists($module_dir . '/composer.json')
                    && file_exists($module_dir . '/module.json')
                    && file_exists($module_dir . '/Config/config.php')
                    && file_exists($data_controller_path))
                ) {
                    \File::deleteDirectory($module_dir);
                    $output = ['success' => false,
                        'msg' => __('messages.something_went_wrong'),

                        // 
                    ];
                    return redirect()->back()->with(['status' => $output]);
                }

                // Clear module assets cache when new module is uploaded
                Cache::forget('module_assets');

                // Publish assets for the uploaded module using its name
                try {
                    Artisan::call('module:publish', ['module' => $module_name, '--force' => true]);
                } catch (\Throwable $e) {
                    // Fallback to publishing all if targeted signature not supported
                    Artisan::call('module:publish');
                }
            }

            $output = ['success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    private function __available_modules()
    {
        return 'a:15:{i:0;O:8:"stdClass":4:{s:1:"n";s:10:"Essentials";s:2:"dn";s:17:"Essentials Module";s:1:"u";s:53:"https://ultimatefosters.com/recommends/essential-app/";s:1:"d";s:49:"Essentials features for every growing businesses.";}i:1;O:8:"stdClass":4:{s:1:"n";s:10:"Superadmin";s:2:"dn";s:17:"Superadmin Module";s:1:"u";s:54:"https://ultimatefosters.com/recommends/superadmin-app/";s:1:"d";s:76:"Turn your POS to SaaS application and start earning by selling subscriptions";}i:2;O:8:"stdClass":4:{s:1:"n";s:11:"Woocommerce";s:2:"dn";s:18:"Woocommerce Module";s:1:"u";s:55:"https://ultimatefosters.com/recommends/woocommerce-app/";s:1:"d";s:36:"Sync your Woocommerce store with POS";}i:3;O:8:"stdClass":4:{s:1:"n";s:13:"Manufacturing";s:2:"dn";s:20:"Manufacturing Module";s:1:"u";s:57:"https://ultimatefosters.com/recommends/manufacturing-app/";s:1:"d";s:70:"Manufacture products from raw materials, organise recipe & ingredients";}i:4;O:8:"stdClass":4:{s:1:"n";s:7:"Project";s:2:"dn";s:14:"Project Module";s:1:"u";s:51:"https://ultimatefosters.com/recommends/project-app/";s:1:"d";s:66:"Manage Projects, tasks, tasks time logs, activities and much more.";}i:5;O:8:"stdClass":4:{s:1:"n";s:6:"Repair";s:2:"dn";s:13:"Repair Module";s:1:"u";s:50:"https://ultimatefosters.com/recommends/repair-app/";s:1:"d";s:248:"Repair module helps with complete repair service management of electronic goods like Cellphone, Computers, Desktops, Tablets, Television, Watch, Wireless devices, Printers, Electronic instruments and many more similar devices which you can imagine!";}i:6;O:8:"stdClass":4:{s:1:"n";s:3:"Crm";s:2:"dn";s:10:"CRM Module";s:1:"u";s:63:"https://ultimatefosters.com/product/crm-module-for-ultimatepos/";s:1:"d";s:39:"Customer relationship management module";}i:7;O:8:"stdClass":4:{s:1:"n";s:16:"ProductCatalogue";s:2:"dn";s:16:"ProductCatalogue";s:1:"u";s:90:"https://codecanyon.net/item/digital-product-catalogue-menu-module-for-ultimatepos/28825346";s:1:"d";s:32:"Digital Product catalogue Module";}i:8;O:8:"stdClass":4:{s:1:"n";s:10:"Accounting";s:2:"dn";s:17:"Accounting Module";s:1:"u";s:82:"https://ultimatefosters.com/product/accounting-bookkeeping-module-for-ultimatepos/";s:1:"d";s:48:"Accounting & Book keeping module for UltimatePOS";}i:9;O:8:"stdClass":4:{s:1:"n";s:12:"AiAssistance";s:2:"dn";s:19:"AiAssistance Module";s:1:"u";s:73:"https://ultimatefosters.com/product/ai-assistance-module-for-ultimatepos/";s:1:"d";s:104:"AI Assistant module for UltimatePOS. This module used openAI API to help with in copywriting & reporting";}i:10;O:8:"stdClass":4:{s:1:"n";s:15:"AssetManagement";s:2:"dn";s:22:"AssetManagement Module";s:1:"u";s:76:"https://ultimatefosters.com/product/asset-management-module-for-ultimatepos/";s:1:"d";s:40:"Useful for managing all kinds of assets.";}i:11;O:8:"stdClass":4:{s:1:"n";s:3:"Cms";s:2:"dn";s:10:"Cms Module";s:1:"u";s:59:"https://ultimatefosters.com/product/ultimatepos-cms-module/";s:1:"d";s:153:"Mini CMS (content management system) Module for UltimatePOS to help manage all frontend contents like Landing page, Blogs, Contact us & many other pages.";}i:12;O:8:"stdClass":4:{s:1:"n";s:9:"Connector";s:2:"dn";s:20:"Connector/API Module";s:1:"u";s:68:"https://ultimatefosters.com/product/rest-api-module-for-ultimatepos/";s:1:"d";s:24:"Provide the API for POS.";}i:13;O:8:"stdClass":4:{s:1:"n";s:3:"Gym";s:2:"dn";s:10:"Gym Module";s:1:"u";s:74:"https://ultimatefosters.com/product/gym-management-module-for-ultimatepos/";s:1:"d";s:37:"Gym Management module for UltimatePOS";}i:14;O:8:"stdClass":4:{s:1:"n";s:3:"Hms";s:2:"dn";s:23:"Hotel Management Module";s:1:"u";s:87:"https://ultimatefosters.com/product/hms-hotel-management-system-module-for-ultimatepos/";s:1:"d";s:119:"Hotel Management System module for UltimatePOS, provides features for room bookings, extras, coupons & related features";}}';
    }
}
