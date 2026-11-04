<?php return array (
  'aloha/twilio' => 
  array (
    'providers' => 
    array (
      0 => 'Aloha\\Twilio\\Support\\Laravel\\ServiceProvider',
    ),
    'aliases' => 
    array (
      'Twilio' => 'Aloha\\Twilio\\Support\\Laravel\\Facade',
    ),
  ),
  'arcanedev/log-viewer' => 
  array (
    'providers' => 
    array (
      0 => 'Arcanedev\\LogViewer\\LogViewerServiceProvider',
      1 => 'Arcanedev\\LogViewer\\Providers\\DeferredServicesProvider',
    ),
  ),
  'barryvdh/laravel-debugbar' => 
  array (
    'aliases' => 
    array (
      'Debugbar' => 'Barryvdh\\Debugbar\\Facades\\Debugbar',
    ),
    'providers' => 
    array (
      0 => 'Barryvdh\\Debugbar\\ServiceProvider',
    ),
  ),
  'barryvdh/laravel-dompdf' => 
  array (
    'aliases' => 
    array (
      'PDF' => 'Barryvdh\\DomPDF\\Facade\\Pdf',
      'Pdf' => 'Barryvdh\\DomPDF\\Facade\\Pdf',
    ),
    'providers' => 
    array (
      0 => 'Barryvdh\\DomPDF\\ServiceProvider',
    ),
  ),
  'consoletvs/charts' => 
  array (
    'providers' => 
    array (
      0 => 'ConsoleTVs\\Charts\\ChartsServiceProvider',
    ),
  ),
  'fruitcake/laravel-debugbar' => 
  array (
    'aliases' => 
    array (
      'Debugbar' => 'Barryvdh\\Debugbar\\Facades\\Debugbar',
    ),
    'providers' => 
    array (
      0 => 'Barryvdh\\Debugbar\\ServiceProvider',
    ),
  ),
  'knox/pesapal' => 
  array (
    'providers' => 
    array (
      0 => 'Knox\\Pesapal\\PesapalServiceProvider',
    ),
    'aliases' => 
    array (
      'Pesapal' => 'Knox\\Pesapal\\Facades\\Pesapal',
    ),
  ),
  'knuckleswtf/scribe' => 
  array (
    'providers' => 
    array (
      0 => 'Knuckles\\Scribe\\ScribeServiceProvider',
    ),
  ),
  'laravel/legacy-factories' => 
  array (
    'providers' => 
    array (
      0 => 'Illuminate\\Database\\Eloquent\\LegacyFactoryServiceProvider',
    ),
  ),
  'laravel/passport' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Passport\\PassportServiceProvider',
    ),
  ),
  'laravel/tinker' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Tinker\\TinkerServiceProvider',
    ),
  ),
  'laravel/ui' => 
  array (
    'providers' => 
    array (
      0 => 'Laravel\\Ui\\UiServiceProvider',
    ),
  ),
  'laravelcollective/html' => 
  array (
    'providers' => 
    array (
      0 => 'Collective\\Html\\HtmlServiceProvider',
    ),
    'aliases' => 
    array (
      'Form' => 'Collective\\Html\\FormFacade',
      'Html' => 'Collective\\Html\\HtmlFacade',
    ),
  ),
  'maatwebsite/excel' => 
  array (
    'aliases' => 
    array (
      'Excel' => 'Maatwebsite\\Excel\\Facades\\Excel',
    ),
    'providers' => 
    array (
      0 => 'Maatwebsite\\Excel\\ExcelServiceProvider',
    ),
  ),
  'milon/barcode' => 
  array (
    'providers' => 
    array (
      0 => 'Milon\\Barcode\\BarcodeServiceProvider',
    ),
    'aliases' => 
    array (
      'DNS1D' => 'Milon\\Barcode\\Facades\\DNS1DFacade',
      'DNS2D' => 'Milon\\Barcode\\Facades\\DNS2DFacade',
    ),
  ),
  'myfatoorah/laravel-package' => 
  array (
    'providers' => 
    array (
      0 => 'MyFatoorah\\LaravelPackage\\MyFatoorahServiceProvider',
    ),
  ),
  'nesbot/carbon' => 
  array (
    'providers' => 
    array (
      0 => 'Carbon\\Laravel\\ServiceProvider',
    ),
  ),
  'nunomaduro/collision' => 
  array (
    'providers' => 
    array (
      0 => 'NunoMaduro\\Collision\\Adapters\\Laravel\\CollisionServiceProvider',
    ),
  ),
  'nunomaduro/termwind' => 
  array (
    'providers' => 
    array (
      0 => 'Termwind\\Laravel\\TermwindServiceProvider',
    ),
  ),
  'nwidart/laravel-menus' => 
  array (
    'providers' => 
    array (
      0 => 'Nwidart\\Menus\\MenusServiceProvider',
    ),
    'aliases' => 
    array (
      'Menu' => 'Nwidart\\Menus\\Facades\\Menu',
    ),
  ),
  'nwidart/laravel-modules' => 
  array (
    'providers' => 
    array (
      0 => 'Nwidart\\Modules\\LaravelModulesServiceProvider',
    ),
    'aliases' => 
    array (
      'Module' => 'Nwidart\\Modules\\Facades\\Module',
    ),
  ),
  'openai-php/laravel' => 
  array (
    'providers' => 
    array (
      0 => 'OpenAI\\Laravel\\ServiceProvider',
    ),
  ),
  'propaganistas/laravel-disposable-email' => 
  array (
    'providers' => 
    array (
      0 => 'Propaganistas\\LaravelDisposableEmail\\DisposableEmailServiceProvider',
    ),
  ),
  'spatie/laravel-activitylog' => 
  array (
    'providers' => 
    array (
      0 => 'Spatie\\Activitylog\\ActivitylogServiceProvider',
    ),
  ),
  'spatie/laravel-backup' => 
  array (
    'providers' => 
    array (
      0 => 'Spatie\\Backup\\BackupServiceProvider',
    ),
  ),
  'spatie/laravel-ignition' => 
  array (
    'aliases' => 
    array (
      'Flare' => 'Spatie\\LaravelIgnition\\Facades\\Flare',
    ),
    'providers' => 
    array (
      0 => 'Spatie\\LaravelIgnition\\IgnitionServiceProvider',
    ),
  ),
  'spatie/laravel-permission' => 
  array (
    'providers' => 
    array (
      0 => 'Spatie\\Permission\\PermissionServiceProvider',
    ),
  ),
  'spatie/laravel-signal-aware-command' => 
  array (
    'providers' => 
    array (
      0 => 'Spatie\\SignalAwareCommand\\SignalAwareCommandServiceProvider',
    ),
    'aliases' => 
    array (
      'Signal' => 'Spatie\\SignalAwareCommand\\Facades\\Signal',
    ),
  ),
  'srmklive/paypal' => 
  array (
    'aliases' => 
    array (
      'PayPal' => 'Srmklive\\PayPal\\Facades\\PayPal',
    ),
    'providers' => 
    array (
      0 => 'Srmklive\\PayPal\\Providers\\PayPalServiceProvider',
    ),
  ),
  'unicodeveloper/laravel-paystack' => 
  array (
    'aliases' => 
    array (
      'Paystack' => 'Unicodeveloper\\Paystack\\Facades\\Paystack',
    ),
    'providers' => 
    array (
      0 => 'Unicodeveloper\\Paystack\\PaystackServiceProvider',
    ),
  ),
  'yajra/laravel-datatables-oracle' => 
  array (
    'providers' => 
    array (
      0 => 'Yajra\\DataTables\\DataTablesServiceProvider',
    ),
    'aliases' => 
    array (
      'DataTables' => 'Yajra\\DataTables\\Facades\\DataTables',
    ),
  ),
);