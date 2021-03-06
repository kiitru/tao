<?php

//TODO: configure to site.php?
Core::configure(array(
	'Templates.HTML' => array(
		'template_class'=>'CMS.Views.View'
	),
	'WS.DSL' => array(
		'middleware' => array(
			'cms_std' => 'CMS.Handlers.StdControlsHandler',
			'cms_configure' => 'CMS.Handlers.Configure',
			'cms_restricted' => 'CMS.Handlers.RestrictedRealms',
			'cms_static' => 'CMS.Handlers.Static',
			'cms_realm_auth' => 'CMS.Handlers.RealmAuth',
		),
		'handlers' => array(
			'cms_action' => 'CMS.Handlers.ActionHandler'
		),
	)
));


/// <module name="CMS" maintainer="gusev@techart.ru" version="0.0.0">

Core::load('WS', 'Events');

/// <class name="CMS" stereotype="module">
///   <implements interface="Core.ModuleInterface" />
///   <depends supplier="CMS.Lang" stereotype="creates" />
///   <depends supplier="CMS.PageNavigator" stereotype="creates" />
///   <depends supplier="CMS.Vars" stereotype="uses" />
///   <depends supplier="CMS.Insertions" stereotype="uses" />
///   <depends supplier="CMS.Admin" stereotype="uses" />
///   <depends supplier="CMS.Handlers" stereotype="uses" />
///   <depends supplier="CMS.Parser" stereotype="uses" />
///   <depends supplier="CMS.WikiParser" stereotype="uses" />
class CMS implements Core_ModuleInterface {
///   <constants>
	const MODULE  = 'CMS';
	const VERSION = '2.0.4';
///   </constants>

	static $libpath		= '';
	static $taopath		= '';

	static $config;
	static $mappers 	= array();
	static $layouts		= array();
	static $default_auth_realm = false;
	static $disable_local_auth = true;
	static $page_401 	= '401';
	static $page_404 	= '404';
	static $page_main 	= 'main';
	static $site		= '__';
	static $site_prefix	= '';
	static $sites;
	static $page;
	static $defsite		= '__';
	static $default_lang 	= 'ru';
	static $forced_lang 	= false;
	static $defsite_title	= 'Default';
	static $default_last_modified = false;
	static $env;
	static $page_navigator	= false;
	static $parser_module	= 'Text.Parser.Parms';//'CMS.Parser';
	static $wiki_parser_module	= 'Text.Parser.Wiki';//'CMS.WikiParser';
	static $nav_module	= 'CMS.Navigation';
	static $vars_module	= 'CMS.Vars2';
	static $admin		= 'admin';
	static $admin_realm	= 'admin';
	static $www		= 'www';
	static $chmod_dir	= 0777;
	static $chmod_file	= 0666;
	static $chgrp_dir	= false;
	static $chgrp_file	= false;
	static $chown_dir	= false;
	static $chown_file	= false;
	static $user_lang	= false;
	static $parser		= false;
	static $wiki_parser	= false;
	static $navigation	= false;
	static $nav_setup	= false;
	static $print_prefix	= false;
	static $print_version	= false;
	static $print_layout	= 'print';
	static $views_path	= '';
	static $app_path	= '../app';
	static $bin_path	= '../bin';
	static $files_path	= '../%files%';
	static $config_path	= '../config';
	static $logs_path	= '../logs';
	static $stdfiles_path	= '';
	static $stdfiles_cache	= 'stdcache';
	static $assets_dir	= 'tao';
	static $check_assets	= true;
	static $globals;
	static $force_layout	= false;
	static $in_admin	= false;
	static $is_cli		= false;
	static $is_offline	= null;
	static $extra_auth	= false;
	static $after_auth	= false;

	static $temp_dir	= false;

	static $navigation_struct = false;

	static $cfg_file	= '../config/site.php';
	static $cfg;
	static $dbDSN;
	static $db = false;
	static $host = false;

	static $index_controller = 'CMS.Controller.Index';
	static $admin_index_controller = 'CMS.Controller.AdminIndex';

	static $root_exception_catcher = 'CMS.ExceptionCatcher';

	static $component_original_names = array();
	static $component_names = array();
	static $component_module_prefix = array();
	static $current_component_name;
	static $current_controller_name;
	static $current_controller;
	static $current_mapper;

	static $plugins_before_dispatch = array();
	static $action_handler_process = false;
	static $commands = array();

	static $css_files = array();
	static $js_files = array();

	static $fields_types = array();

	static $restricted_realms = array();
	static $original_uri = '/';

	static $i_view = false;

	static $i_stdstyles = array();
	static $i_stdscripts = array();

	static $registered_objects = array();
	static $created_objects = array();
	static $objects_builder = false;

	static $db_connections = array();
	static $orm_root = false;
	static $orm_autoload = true;
	
	static protected $vars;
	
	static protected $web_application;
	static protected $common_application;
	static protected $dispatcher;
	static protected $enable_rest = false;
	static protected $enable_dev_components;

	static protected $listeners = array();

	static public function env() {
		return self::$env;
	}

/// <protocol name="creating">
	static protected function configure_paths() {
		$path_info = IO_FS::Path(__FILE__);
		self::$libpath = $path_info->dirname;
		
		self::$taopath = rtrim(self::$libpath,'/');
		self::$taopath = preg_replace('{[a-z0-9_-]+$}i','',self::$taopath);
		self::$taopath = rtrim(self::$taopath,'/');
		
		self::$stdfiles_path = self::$taopath.'/files';
		self::$views_path = self::$taopath.'/views';
		self::check_assets();

		if ($m = Core_Regexps::match_with_results('{/([^/]+)$}',rtrim($_SERVER['DOCUMENT_ROOT'],'/'))) {
			CMS::$www = $m[1];
		}
	}
  
	static protected function configure_permissions() {
		IO_FS::options(array(
			'dir_mod'  => self::$chmod_dir,
			'file_mod' => self::$chmod_file,
			'dir_own'  => self::$chown_dir,
			'file_own' => self::$chown_file,
			'dir_grp'  => self::$chgrp_dir,
			'file_grp' => self::$chgrp_file,
		));
	}

	static protected function configure_loader() {
		Core::load('Core.Loader');
		$base_path = self::$app_path;
		if (self::$enable_dev_components) {
			$base_path = str_replace('app', '(dev|app)', $base_path);
		}
		Core::loader(Core_Loader::extended())->paths(array(
			'---Component.{name}.App' => $base_path . '/components/{name}/app(/lib)?',
			'-Component.{name}' => array($base_path . '/components(/{name}/lib)?', $base_path . '/components/{name}(/lib)?'),
			'--Component.{name}' => $base_path . '/components/{name}/lib',
		));
	}

	static public function ws_status_listener($response) {
		if ($response->body instanceof Templates_Template)
			CMS::layout_view($response->body);
	}

	static protected function add_listeners() {
		Events::add_listener('ws.status', array('CMS', 'ws_status_listener'));
		foreach (self::$listeners as $e => $l)
			Events::add_listener($e, $l);
	}

///   <method scope="class" name="initialize">
///     <args>
///       <arg name="config" type="array" default="array()" />
///     </args>
///     <body>
	static function initialize($config=array()) {
		try {
			self::$globals = new ArrayObject();
			foreach($config as $key => $value) self::$$key = $value;

			self::add_listeners();

			foreach(array('files_path','stdfiles_cache','assets_dir') as $key) self::$$key = str_replace('%files%',Core::option('files_name'),self::$$key);

			self::configure_paths();
			self::configure_permissions();

			self::configure_loader();
			
			Core::load('Events');
			$rc = Events::call('cms.initialize.start');
			if (!is_null($rc)) return $rc;
			
			Core::load('WS.DSL');
			self::$common_application = WS_DSL::application();
			self::$web_application = WS_DSL::application();

			self::common_application()->cms_static();
			self::dummy_run();

			if (!Core::option('spl_autoload'))
				self::load();
			else
				spl_autoload_register('CMS::spl_autoload');

			
			self::common_application()
				->config(self::$cfg_file)
				->db()
				->cache()
				->cms_configure()
				->cms_restricted();

			if (CMS::$orm_autoload) self::common_application()->orm(CMS::orm());//TODO: не создавать объект
			//FIXME: не запускать сразу
			self::dummy_run();

			$rc = Events::call('cms.initialize.ready');
			if (!is_null($rc)) return $rc;
			
			Core::load(self::vars_module());
			
			self::application()
				->status(array(404 => '404', 500), 'status', true)
				->cms_std()
				->auth_basic(CMS_Handlers::AuthModule(), array('env_name' => 'admin_auth'))
				->cms_realm_auth()
				;
			if (self::$enable_rest) {
				Core::load('WS.Services.REST');
				Core::load('CMS.Application');
				self::$dispatcher = new CMS_Application_Dispatcher();
				WS_DSL::add_middleware('dispatcher', self::$dispatcher);
			}
		}

		catch(Exception $e) {
			self::root_catcher($e);
		}
	}
///     </body>
///   </method>

	static public function spl_autoload($class) {
  		if (in_array($class, array('CMS_Mapper','CMS_Router')))
  			Core::loader()->load('CMS.Controller');
	}

	static function dispatcher() {
		return self::$dispatcher;
	}
	
	static public function vars_module($value = false) {
		if (is_string($value))
			return self::$vars_module = $value;
		return self::$vars_module;
	}
	
	static public function vars() {
		if (isset(self::$vars)) return self::$vars;
		self::$vars = Core::make(self::$vars_module);
		if (method_exists(self::$vars,'setup')) self::$vars->setup();
		return self::$vars;
	}

	static function load() {
		//TODO: убрать и подгружать по необходимости
		Core::load('Events');
		Core::load('CMS.Component');
		Core::load('Templates.HTML');
		Core::load('Templates.HTML.Forms');
		Core::load('Templates.HTML.Assets');
		Core::load('Forms');
		Core::load('IO.FS');
		Core::load('CMS.Controller');
		Core::load('CMS.Dumps');
		Core::load('CMS.Admin');
		Core::load('Text.Insertions');
		Core::load('CMS.Insertions');
		Core::load('CMS.PageNavigator');
	}


	static public function application() {return self::$web_application;}
	
	static public function common_application() {return self::$common_application;}
	
	static protected function dummy_run($name = 'common_application') {return self::$name()->dummy_service()->run(WS::env());}

	static function check_assets_symlink($path) {
		if (is_link($path)) return;
		if (!function_exists('symlink')) throw new CMS_Exception("Error creating symlink $path (function not exists)");
		if (is_dir($path)) CMS::rmdir($path);
		$dir = IO_FS::File($path)->dir_name;
		CMS::mkdirs($dir);
		symlink(self::$stdfiles_path,$path);
	}

	static function check_assets() {
		if (!self::$check_assets) return;
		if (!isset($_SERVER['HTTP_HOST'])&&!isset($_SERVER['REQUEST_URI'])) return;
		self::check_assets_symlink('./'.self::$assets_dir);
	}

	static function copy_assets() {
		self::copy(self::$stdfiles_path,'./'.self::$assets_dir);
	}
	
	//TODO: сделать callback
	static protected function before_run($env) {
		Events::call('cms.run');
 		$env->urls = WebKit_Controller::Mapper();
		foreach(CMS::$mappers as $name => $mapper) $env->urls->map(strtolower($name),$mapper);
	}
	
	static function is_offline() {
		if (is_null(self::$is_offline)) {
			if (isset($_SERVER['HTTP_USER_AGENT'])) {
				self::$is_offline = Core_Regexps::match('{teleport}i',$_SERVER['HTTP_USER_AGENT']);
			}
			self::$is_offline = false;
		}
		return self::$is_offline;
	}

///   <method scope="class" name="run">
///     <brief>Производит запуск веб-приложения</brief>
///     <body>
	public function run() {

		try {
			if (!isset(self::$component_names['CMSStockroom'])) {
				Core::load('CMS.Stockroom');
			}
			if (!isset(self::$component_names['CMSFSPages'])) {
				Core::load('CMS.FSPages');
			}
			self::before_run(CMS::env());
			// Если скрипт запущен из командной строки, то веб-приложение не запускается
			//TODO: create cli_application
			if (!isset($_SERVER['HTTP_HOST'])&&!isset($_SERVER['REQUEST_URI'])) {
				CMS::$is_cli = true;
				Core::load('CMS.CLI');
				CMS_CLI::run();
				return;
			}
			if (self::$enable_rest)
				self::application()->dispatcher();
				
			return WS::run(
					self::application()->
					cms_action()
			);
		}

		catch(Exception $e) {
			self::root_catcher($e);
		}
	}
///     </body>
///   </method>



///   <method scope="class" name="root_catcher">
///     <brief>Корневой перехватчик исключений</brief>
///     <body>
	public function root_catcher($e) {
		Core::load(self::$root_exception_catcher);
		$class = str_replace('.','_',self::$root_exception_catcher);
		call_user_func(array($class,'run'),$e);
	}
///     </body>
///   </method>


///   <method scope="class" name="cached_run">
///     <brief>Кешированный вызов. Метод run переданного модуля вызывается только один раз после модификации файла модуля.</brief>
///     <body>
	public function cached_run($module,$method='run') {
		$class = Core_Types::real_class_name_for($module);
		$key = "cms:cached_run:{$class}";
		if ($method!='run') {
			$key .= "_{$method}";
		}
		$tc = (int)WS::env()->cache->get($key);
		$module_file = Core::loader()->file_path_for($module);
		$tm = filemtime($module_file);
		if ($tc<$tm) {
			Core::load($module);
			Core::call($class,$method)->invoke();
			WS::env()->cache->set($key,$tm,0);
		}
	}
///     </body>
///   </method>


/// </protocol>






/// <protocol name="building">


///   <method scope="class" name="lang" returns="CMS.Lang">
///     <brief>Возвращает экземпляр языкового модуля (для многоязычных сайтов)</brief>
///     <body>
	static function lang($code=false,$force=false) {
		if (!self::$user_lang) {
			Core::load('CMS.Lang');
			self::$user_lang = new CMS_Lang();
			self::$user_lang->init_module(self::site());
		}
		if ($code||is_string($code)) {
			$code = (string)$code;
			if ($m = Core_Regexps::match_with_results('{^([^:]+):([^:]*):([^:]+)$}',$code)) {
				$lang = trim($m[1]);
				$comp = trim($m[2]);
				$parm = trim($m[3]);
				if ($lang=='lang') {
					$l = CMS::site_lang();
					if ($force) CMS::site_set_lang($force);
					$rc = self::$user_lang->$comp->$parm;
					CMS::site_set_lang($l);
					return $rc;
				}
			}
			return self::$user_lang->transform($code,$force);
		}
		return self::$user_lang;
	}
///     </body>
///   </method>

///   <method scope="class" name="navigation" returns="CMS.Navigation">
///     <brief>Возвращает экземпляр объекта навигации сайта</brief>
///     <args>
///       <arg name="set" type="boolean" default="false" />
///     </args>
///     <body>
	static function navigation($set=false) {
		if (!self::$navigation) {
			self::process_navigation();
		}

		if (!self::$nav_setup) {
			self::$nav_setup = true;
			self::$navigation->setup_selected();
		}
		return is_string($set)?self::$navigation[$set]:self::$navigation;
	}
///     </body>
///   </method>


///   <method scope="class" name="objects" returns="object">
///     <brief>Возвращает экземпляр модуля взаимодействия компонентов</brief>
///     <body>
	static function objects() {
		if (!self::$objects_builder) self::$objects_builder = new CMS_ObjectsBuilder();
		return self::$objects_builder;
	}
///     </body>
///   </method>


///   <method scope="class" name="db" returns="object">
///     <brief>Возвращает подключение к базе данных</brief>
///     <body>
//TODO: замениться на WS::env->db->name
	static function db($name='__default') {
		if (!isset(self::$db_connections[$name])||!self::$db_connections[$name]) {
			if ($name=='__default') {
				self::$db_connections[$name] = self::$db;
			}
			else {
				self::$db_connections[$name] = DB::Connection(self::$cfg->database->$name);
			}
		}
		return self::$db_connections[$name];
	}
///     </body>
///   </method>

///   <method scope="class" name="orm" returns="object">
///     <brief>Возвращает корневой ORM-маппер</brief>
///     <body>
	static function orm() {
		if (!self::$orm_root) {
			Core::load('CMS.ORM');
			self::$orm_root = new CMS_ORM_Root();
		}
		return self::$orm_root;
	}
///     </body>
///   </method>


/// </protocol>













/// <protocol name="quering">

///   <method scope="class" name="admin" returns="boolean">
///     <brief>Определяет находимся ли мы в данный момент в админе</brief>
///     <body>
	static function admin() { return self::$in_admin; }
///     </body>
///   </method>


///   <method scope="class" name="www" returns="string">
///     <brief>Возвращает имя каталога DOCUMENT_ROOT (не полный путь, а только последный подкаталог - он может по-разному называться на разный хостингах)</brief>
///     <body>
	static function www() {
		$www = trim(self::$www);
		return $www==''?'www':$www;
	}
///     </body>
///   </method>


///   <method scope="class" name="temp_dir" returns="string">
///     <brief>Возвращает имя каталога для хранения временных файлов</brief>
///     <body>
	static function temp_dir() {
		if (self::$temp_dir === true) return rtrim(sys_get_temp_dir(),'/');
		if (is_string(self::$temp_dir)) return rtrim(self::$temp_dir,'/');
		if (isset(self::$cfg->site) && $dir = self::$cfg->site->temp_dir) return rtrim($dir,'/');
		if (!IO_FS::exists('../'.Core::option('files_name').'/tmp')) @self::mkdirs('../'.Core::option('files_name').'/tmp');
		if (IO_FS::exists('../'.Core::option('files_name').'/tmp')) return '../'.Core::option('files_name').'/tmp';
		if (!IO_FS::exists('./'.Core::option('files_name').'/tmp')) self::mkdirs('./'.Core::option('files_name').'/tmp');
		return './'.Core::option('files_name').'/tmp';
	}
///     </body>
///   </method>


///   <method scope="class" name="site_dir" returns="string">
///     <body>
	static function site_dir() {
		$s = getcwd();
		$s = preg_replace('{[^/]+$}','',$s);
		return $s;
	}
///     </body>
///   </method>


///   <method scope="class" name="component_exists" returns="boolean">
///     <brief>Возвращает true если компонент с заданным именем зарегистрирован в системе</brief>
///     <args>
///       <arg name="name" type="string" />
///     </args>
///     <body>
	static function component_exists($name) {
		return isset(self::$component_names[$name]);
	}
///     </body>
///   </method>


///   <method scope="class" name="component_class_name" returns="string|false">
///     <brief>Возвращает имя класса - компонета с заданным именем или false если такого компонента не зарегистрировано</brief>
///     <args>
///       <arg name="name" type="string" />
///     </args>
///     <body>
	static function component_class_name($name) {
		if (!self::component_exists($name)) return false;
		return self::$component_names[$name];
	}
///     </body>
///   </method>


///   <method scope="class" name="ip" returns="string">
///     <brief>Возвращает удаленный IP</brief>
///     <body>
	static function ip() {
		if (isset($_SERVER['X_REAL_IP'])) return $_SERVER['X_REAL_IP'];
		if (isset($_SERVER['HTTP_X_REAL_IP'])) return $_SERVER['HTTP_X_REAL_IP'];
		return $_SERVER['REMOTE_ADDR'];
	}
///     </body>
///   </method>


///   <method scope="class" name="site_title" returns="string">
///     <brief>Возвращает наименование сайта или домен если не задано</brief>
///     <details>
///       В качестве аргумента передается падеж.
///     </details>
///     <args>
///       <arg name="p" type="string" />
///     </args>
///     <body>
	static function site_title($p=false) {
		$title = false;
		$titlei = self::$cfg->site->title;
		if (!$titlei) $titlei = $_SERVER['HTTP_HOST'];
		switch($p) {
			case 'r':
				$title = self::$cfg->site->titler;
				break;
			case 'd':
				$title = self::$cfg->site->titled;
				break;
			case 'v':
				$title = self::$cfg->site->titlev;
				break;
			case 't':
				$title = self::$cfg->site->titlet;
				break;
			case 'p':
				$title = self::$cfg->site->titlep;
				break;
		}
		if (!$title) $title = $titlei;
		return $title;
	}
///     </body>
///   </method>


///   <method scope="class" name="is_local" returns="boolean">
///     <brief>Возвращает true если приложение исполняется на локальном проекте (не на хостинге)</brief>
///     <body>
	static function is_local() {
		if (isset(self::$cfg->site)) {
			$local = trim(self::$cfg->site->local);
			if (self::check_yes($local)) return true;
		}
		return isset($_SERVER['IS_TECHART']);
	}
///     </body>
///   </method>

///   <method scope="class" name="site" returns="string">
///     <brief>Возвращает код текущего сайта (для многосайтовых конфигураций)</brief>
///     <body>
	static function site() {
		return self::$site;
	}
///     </body>
///   </method>

///   <method scope="class" name="site_lang" returns="string">
///     <brief>Возвращает язык текущего сайта (для многосайтовых конфигураций) или админа</brief>
///     <body>
	static function site_lang() {
		if (self::$forced_lang) return self::$forced_lang;
		if (CMS::admin()) return CMS_Admin::$lang;
		$data = self::$sites[self::$site];
		if (!isset($data)) return self::$default_lang;
		if (!isset($data['lang'])) return self::$default_lang;
		return $data['lang'];
	}
///     </body>
///   </method>

///   <method scope="class" name="site_set_lang">
///     <brief>Устанавливает язык интерфейса вне зависимости от настроек текущего сайта и админа</brief>
///     <body>
	static function site_set_lang($lang) {
		self::$forced_lang = $lang;
		Core::load('CMS.Lang');
		CMS_Lang::reset();
	}
///     </body>
///   </method>


///   <method name="site_prefix">
///     <brief>Возвращает URI-префикс текущего или указанного (если передан параметр) сайта (для многосайтовых конфигураций)</brief>
///     <body>
	static function site_prefix($site=false) {
		if (!$site) return self::$site_prefix;
		if (isset(self::$sites[$site])) {
			$value = trim(self::$sites[$site]['prefix']);
			$value = trim($value,'');
			if ($value=='') return '';
			return "/$value";
		}
		return '';
	}
///     </body>
///   </method>

///   <method name="site_host">
///     <brief>Возвращает доменное имя указанного сайта (для многосайтовых конфигураций)</brief>
///     <body>
	static function site_host($site) {
		$rhost = self::$env->request->host;
		if (isset(self::$sites[$site])) {
			$host = trim(self::$sites[$site]['host']);
			$hostname = trim(self::$sites[$site]['hostname']);
			if ($hostname!='') return $hostname;
			if ($host!=''&&$host[0]!='{'&&$host[0]!='/') return $host;
		}
		return $rhost;
	}
///     </body>
///   </method>


///   <method name="component_dir">
///     <brief>Возвращает путь к каталогу указанного компонента</brief>
///     <body>
	static function component_dir($component,$dir=false) {
		 $rc = self::$app_path.'/components/'.$component;
		 if ($dir) $rc .= "/$dir";
		 return $rc;
	}
///     </body>
///   </method>

///   <method name="current_component_dir">
///     <brief>Возвращает путь к каталогу текущего (в данный момент работающего) компонента</brief>
///     <body>
	static function current_component_dir($dir=false) {
		$rc = self::component_dir(self::$current_component_name);
		if ($dir) $rc .= "/$dir";
		return $rc;
	}
///     </body>
///   </method>

///   <method name="static_url">
///     <brief>Возвращает URL для скачивания статического файла из каталога текущего компонента</brief>
///     <body>
	static function static_url($file,$component=false) {
		$path = self::component_static_path($file, $component);
		$url = Templates_HTML::extern_filepath($path);
		return $url;
		// OLD
		// if (!$component) $component = self::$current_component_name;
		// $path = self::component_dir($component)."/$file";
		// $m = IO_FS::exists($path)? filemtime($path) : "0";
		// return "/component-static/$component/$file/$m/";
	}
///     </body>
///   </method>

///   <method name="component_static_path">
///     <brief>Возвращает конструкцию вида file://.... для файла из каталога текущего компонента</brief>
///     <body>
	static function component_static_path($file,$component=false) {
		if (!$component) $component = self::$current_component_name;
		$app_path = self::component_dir($component)."/app/$file";
		if (is_file($app_path)) {
			return "file://".$app_path;
		}
		$path = self::component_dir($component)."/$file";
		return "file://".$path;
	}
///     </body>
///   </method>

/// </protocol>














/// <protocol name="processing">

///   <method scope="class" name="maillist" returns="string">
///     <brief>Производит рассылку по майллисту.</brief>
///     <args>
///       <arg name="mail" type="Mail.Message" />
///       <arg name="list" type="array" />
///       <arg name="dir" type="string" />
///     </args>
///     <body>
	static function maillist($mail,$list,$dir='../bin/maillist') {
		$dir = rtrim($dir,'/');
		CMS::mkdirs("$dir/messages");
		CMS::mkdirs("$dir/recipients");
		Core::load('Mail.List');
		Mail_List::option('root',$dir);

		$emails = array();
		foreach($list as $k => $item) {
			if (is_string($k)&&is_string($item)) $item = array(
				'To' => $k,
				'Unsubscribe-List' => $item,
				'UNSUBSCRIBE' => $item,
			);
			else if (is_string($item)) $item = array('To'=>$item);
			if (is_string($k)&&!isset($item['To'])) $item['To'] = $k;
			$emails[] = $item;
		}

		Mail_List::Spawner($mail, $emails)->id(time().rand(1111,9999))->spawn();
	}
///     </body>
///   </method>


///   <method scope="class" name="process_insertions" returns="string">
///     <brief>Производит замену всех вставок (Insertions).</brief>
///     <args>
///       <arg name="source" type="string" />
///     </args>
///     <body>
	static function process_insertions($src) {
	  return Text_Insertions::filter()->process($src);
	}
///     </body>
///   </method>


///   <method scope="class" name="parse_parms" returns="array">
///     <brief>Парсит текст, превращая его в массив</brief>
///     <details>
///       Текст должен быть в соответствующем формате. В этом формате описывается, например, навигация сайта.
///     </details>
///     <args>
///       <arg name="src" type="string" />
///     </args>
///     <body>
	static function parse_parms($src) {
		if (!self::$parser) {
			Core::load(self::$parser_module);
			self::$parser = Core_Types::reflection_for(str_replace('.','_',self::$parser_module))->newInstance();
		}
		return self::$parser->parse($src);
	}
///     </body>
///   </method>

///   <method scope="class" name="unparse_parms" returns="string">
///     <brief>Производит действие, обратное parse_parms</brief>
///     <details>
///       На входе должен быть итератор. В противном случае производится простое приведение к строковому типу
///     </details>
///     <args>
///       <arg name="src" type="iterable" />
///     </args>
///     <body>
	static function unparse_parms($src) {
		if (!self::$parser) {
			Core::load(self::$parser_module);
			self::$parser = Core_Types::reflection_for(str_replace('.','_',self::$parser_module))->newInstance();
		}
		return self::$parser->unparse($src);
	}
///     </body>
///   </method>

///   <method scope="class" name="parse_wiki" returns="string">
///     <brief>Трансформирует wiki-разметку в HTML</brief>
///     <args>
///       <arg name="src" type="string" />
///     </args>
///     <body>
	static function parse_wiki($src) {
		if (!self::$wiki_parser) {
			Core::load(self::$wiki_parser_module);
			self::$wiki_parser = Core_Types::reflection_for(str_replace('.','_',self::$wiki_parser_module))->newInstance();
		}
		return self::$wiki_parser->parse($src);
	}
///     </body>
///   </method>


///   <method scope="class" name="abs_refs" returns="string">
///     <brief>В переданном тексте заменяет относительные ссылки на абсолютные (в текущем домене)</brief>
///     <args>
///       <arg name="source" type="string" />
///     </args>
///     <body>
	static function abs_refs($s) {
		$s = preg_replace_callback('{<a([^>]+)href="([^"]+)"}ism',array(self,'abs_refs_cb'),$s);
		return $s;
	}
///     </body>
///   </method>



/// </protocol>




/// <protocol name="performing">


///   <method name="log">
///     <args>
///       <arg name="name" type="string" />
///       <arg name="text" type="string" />
///     </args>
///     <body>
	static function log($name,$text) {
		$text = trim($text);
		$f = fopen("../logs/$name.log","a");
		$t = date('Y-m-d G:i:s');
		$ip = self::ip();
		fputs($f, "$t [$ip] $text\n");
		fclose($f);
	}
///     </body>
///   </method>


///   <method scope="class" name="add_component">
///     <brief>Регистрирует в системе компонент</brief>
///     <details>
///       Этот метод рекомендуется вызывать в методе initialize класса - модуля компонента.
///     </details>
///     <args>
///       <arg name="name" type="string" brief="Название компонента" />
///       <arg name="mapper" type="WebKit.Controller.AbstractMapper" brief="Экземпляр маппера, который будет обрабатывать запросы для данного компонента" />
///       <arg name="layout" type="" default="work" brief="Layout по умолчанию, в котором будут отображаться страницы компонента" />
///     </args>
///     <body>
	static protected $components = array();

	static public function add_component_object($obj, $mapper, $layout='work') {
		$name = $obj->name;
		self::$components[$name] = $obj;
		self::add_component($name, $mapper, $layout);
		if ($obj->is_auto_schema()) {
			$obj->process_schema();
		}
	}

	static public function get_component_name_for($object) {
		$name = null;
		$class = Core_Types::real_class_name_for($object);
		$parts = explode('_', $class);
		if ($parts[0] == 'Component')
			$name = $parts[1];
		return $name;
	}

	static public function component_for($object) {
		$name = self::get_component_name_for($object);
		return self::component($name);
	}

	static public function component($name = null) {
		if (is_null($name)) {
			if (self::$current_component_name) $name = self::$current_component_name;
		}
		return isset(self::$components[$name]) ? self::$components[$name] : null;
	}

	static function add_component($name,$mapper,$layout='work') {
		self::$component_original_names[strtolower($name)] = $name;
		self::$component_names[strtolower($name)] = "Component_$name";
		self::$component_module_prefix[strtolower($name)] = "Component.$name";
		self::$component_names[$name] = "Component_$name";
		if (is_string($mapper)) $mapper = Core::make($mapper);
		if (self::$enable_rest && ($mapper instanceof WS_Services_REST_Application || is_array($mapper))) {
			$app = is_array($mapper) ? $mapper : array();
			if (is_string($mapper)) $app['class'] = $mapper;
			if (is_object($mapper)) {
				$app['instance'] = $mapper;
				$mapper->name = $name;
			}
			if (!isset($app['prefix'])) $app['prefix'] = Core_Strings::downcase($name);
			self::$dispatcher->map($name, $app);
		}
		else {
			self::$mappers[$name] = $mapper;
		}
		self::$layouts[$name] = $layout;
		$dir = self::component_dir($name);
		Text_Insertions::filter()->add_views_paths(array(
			$dir.'/app/views',
			$dir.'/views',
		));
	}
///     </body>
///   </method>

///   <method scope="class" name="register_insertions" varargs="true">
///     <brief>Регистрирует в системе "вставку" (Insertion)</brief>
///     <details>
///       Вствка (Insertion) - конструкция вида %ИМЯ{параметры}, которую можно использовать в администрируемом контенте, и которая будет заменяться на выводимой странице по определенному алгоритму.
///       В данной функции необходимо указать класс и произвольное количество статических методов в нем, которые будут обрабатывать вставки.
///     </details>
///     <args>
///       <arg name="class" type="string" brief="Имя класса, в котором находится метод" />
///       <arg name="method" type="string" brief="Имя вставки(метода) или 'вставка:метод' если имя метода отличается от имени вставки" />
///     </args>
///     <body>
	static function register_insertions() {
		$args = func_get_args();
		$class = $args[0];
		array_shift($args);
		foreach($args as $arg) {
			$name = $arg;
			$func = $arg;
			if ($m = Core_Regexps::match_with_results('/^(.+):(.+)$/',$arg)) {
				$name = $m[1];
				$func = $m[2];
			}
			Text_Insertions::register_filter(array(strtolower($name) => new Core_Call($class, $func)));
		}
	}
///     </body>
///   </method>

///   <method scope="class" name="register_object" varargs="true">
///     <brief>Регистрирует в системе объект взаимодействия компонентов</brief>
///     <args>
///       <arg name="name" type="string" brief="Имя объекта" />
///       <arg name="module" type="string" brief="Имя модуля" />
///     </args>
///     <body>
	static function register_object($name,$module) {
		self::$registered_objects[$name] = $module;
	}
///     </body>
///   </method>



///   <method scope="class" name="add_command" varargs="true">
///     <brief>Добавляет комманду в очередь</brief>
///     <args>
///       <arg name="chapter" type="string" brief="Имя раздела (получателя)" />
///       <arg name="command" type="string" brief="Комманда (имя метода)" />
///     </args>
///     <details>
///       Этот механизм предназначен для взаимодействия компонентов CMS между собой. Один компонент может послать другому сигнал, не заботясь о том, установлен ли компонент-адресат.
///     </details>
///     <body>
	static function add_command() {
		$args = func_get_args();
		$chapter = trim($args[0]);
		$method = trim($args[1]);
		array_splice($args,0,2);
		if (!isset(self::$commands[$chapter])) self::$commands[$chapter] = array();
		self::$commands[$chapter][] = array(
			'method' => $method,
			'args' => $args,
		);
	}
///     </body>
///   </method>


///   <method scope="class" name="on_before_dispatch">
///     <brief>Добавляет в очередь функцию, которая должна быть выполнениа непосредственно перед диспетчеризацией HTTP-запроса.</brief>
///     <args>
///       <arg name="class" type="string" brief="Имя класса, в котором находится функция" />
///       <arg name="method" type="string" brief="Имя метода" />
///     </args>
///     <body>
	static function on_before_dispatch($class,$method) {
		self::$plugins_before_dispatch[$class] = $method;
	}
///     </body>
///   </method>


///   <method scope="class" name="calc_pages">
///     <brief>Вычисляет количество страниц и валидность номера страницы для постраничной навигации какого-либо списка</brief>
///     <args>
///       <arg name="count" type="int" brief="Количество записей в списке" />
///       <arg name="per_page" type="int" brief="Количество записей на одной странице" />
///       <arg name="page_number" type="int" brief="Номер текущей страницы (передается по ссылке)" />
///     </args>
///     <details>
///       В случае если номер текущей страницы неправильный (меньше единицы или больше числа страниц), то он будет установлен равным единице.
///     </details>
///     <body>
	static function calc_pages($cnt,$perpage,&$page) {
		if ($page<1) $page = 1;
		$num_pages = $cnt/$perpage;
		if (floor($num_pages)!=$num_pages) $num_pages = floor($num_pages)+1;
		if ($num_pages<1) $num_pages = 1;
		if ($page>$num_pages) $page = 1;
		return $num_pages;
	}
///     </body>
///   </method>

///   <method scope="class" name="page_navigator" returns="string">
///     <brief>Возвращает HTML-код постраничного навигатора.</brief>
///     <details>
///       Шаблон навигатора находится в файле app/views/page-navigator.phtml. Если же такого файла нет, то будет использован стандартный tao/views/page-navigator.phtml.
///     </details>
///     <args>
///       <arg name="page" type="int" brief="Номер текущей страницы" />
///       <arg name="num_of_pages" type="int" brief="Количество страниц" />
///       <arg name="url_template" type="string" brief="Шаблон URL, в котором в качестве места для номера страницы стоит знак %" />
///     </args>
///     <body>
	static function page_navigator($page,$numpages,$url) {
		return self::$page_navigator->invokeArgs(NULL,array($page,$numpages,$url));
	}
///     </body>
///   </method>



///   <method scope="class" name="translit" returns="string">
///     <brief>Преобразует в строке русские буквы в транслит</brief>
///     <args>
///       <arg name="value" type="string" />
///     </args>
///     <body>
	static function translit($s) {
		return Core::make('Text.Process')->process($s, 'translit');
	}
///     </body>
///   </method>

///   <method scope="class" name="check_yes" returns="boolean">
///     <brief>Возвращает true если строковой параметр равен одному из значений: '1','yes','true','on'</brief>
///     <args>
///       <arg name="value" type="string" />
///     </args>
///     <body>
	static function check_yes($s) {
		return in_array($s,array('1','yes','true','on'));
	}
///     </body>
///   </method>

///   <method scope="class" name="check_no" returns="boolean">
///     <brief>Возвращает true если строковой параметр равен одному из значений: '0','none','no','false','off'</brief>
///     <args>
///       <arg name="value" type="string" />
///     </args>
///     <body>
	static function check_no($s) {
		return in_array($s,array('0','none','no','false','off',''));
	}
///     </body>
///   </method>

///   <method scope="class" name="setup_meta">
///     <brief>Устанавливает мета-теги если таковые описаны в переданном хеше - в элементах meta.title, meta.description, meta.keywords</brief>
///     <args>
///       <arg name="parms" type="array|Data.Hash" />
///     </args>
///     <body>
	static function setup_meta($parms) {
	  foreach ($parms as $name => $value)
      if (preg_match('{meta\.(.*)}', $name, $m))
        self::env()->meta->{$m[1]} = $value;
	}
///     </body>
///   </method>

///   <method scope="class" name="render_view" returns="CMS.Views.TemplateView">
///     <args>
///       <arg name="template" type="string" />
///       <arg name="parms" type="array" />
///       <arg name="layout" type="string" default="false" />
///     </args>
///     <body>
	static function render_view($tpl,$parms=array(),$layout=false) {
		Core::load('CMS.Views');
		$view = Templates_HTML::Template($tpl)->with($parms);
		if ($layout) $view->within_layout($layout);
		return $view;
	}
///     </body>
///   </method>

///   <method scope="class" name="render" returns="string">
///     <args>
///       <arg name="template" type="string" />
///       <arg name="parms" type="array" />
///       <arg name="layout" type="string" default="false" />
///     </args>
///     <body>
	static function render($tpl,$parms=array(),$layout=false) {
		$view = self::render_view($tpl,$parms,$layout);
		return $view->as_string();
	}
///     </body>
///   </method>

///   <method scope="class" name="render_in_page" returns="string">
///     <args>
///       <arg name="template" type="string" />
///       <arg name="parms" type="array" />
///     </args>
///     <body>
	static function render_in_page($tpl,$parms=array()) {
		if (!self::$i_view) return self::render($tpl,$parms);
		return self::$i_view->partial($tpl,$parms);
	}
///     </body>
///   </method>

	static function render_in_page_cache($tpl,$parms=array()) {
		if (!self::$i_view) return self::render($tpl,$parms);
		return self::$i_view->partial_cache($tpl,$parms);
	}



///   <method scope="class" name="units" returns="string">
///     <brief>Возвращает единицу измерения в числе и падеже, соответствующем заданному числу</brief>
///     <details>
///       Вызываем CMS::units($n,'рубль','рубля','рублей');
///       При $n, равном 1,21,161 и т.д., вернет 'рубль'
///       При $n, равном 2,22,162 и т.д., вернет 'рубля'
///       При $n, равном 5,11,168 и т.д., вернет 'рублей'
///     </details>
///     <args>
///       <arg name="number" type="int" brief="Число" />
///       <arg name="ei" type="string" brief="Единственное число, именительный падеж" />
///       <arg name="er" type="string" brief="Единственное число, родительный падеж" />
///       <arg name="mr" type="string" brief="Множественное число, родительный падеж" />
///     </args>
///     <body>
	static function units($num,$ei,$er,$mr) {
		$num = (int)$num;
		if ($num>10&&$num<15) return $mr;
		$num = $num % 10;
		if ($num==0) return $mr;
		if ($num==1) return $ei;
		if ($num<5)  return $er;
		return $mr;
	}
///     </body>
///   </method>


///   <method scope="class" name="close_tags" returns="string">
///     <brief>Находит в HTML-тексте незакрытые теги и закрывает их</brief>
///     <args>
///       <arg name="html" type="string" brief="HTML-текст" />
///     </args>
///     <body>
	static function close_tags($html) {
		$single_tags = array('meta','img','br','link','area','input','hr','col','param','base');
		preg_match_all('~<([a-z0-9]+)(?: .*)?(?<![/|/ ])>~iU', $html, $result);
		$openedtags = $result[1];
		preg_match_all('~</([a-z0-9]+)>~iU', $html, $result);
		$closedtags = $result[1];
		$len_opened = count($openedtags);
		if (count($closedtags) == $len_opened) {
			return $html;
		}
		$openedtags = array_reverse($openedtags);
		for ($i=0; $i < $len_opened; $i++) {
			if (!in_array($openedtags[$i], $single_tags)) {
				if (FALSE !== ($key = array_search($openedtags[$i], $closedtags))) {
					unset($closedtags[$key]);
				}
				else {
					$html .= '</'.$openedtags[$i].'>';
				}
			}
		}
		return $html;
	}
///     </body>
///   </method>

///   <method scope="class" name="html_clear" returns="string">
///     <brief>Удаляет из HTML-текста теги (кроме IMG) и пробельные символы (в т.ч. и nbsp) для детекта пустого текста</brief>
///     <args>
///       <arg name="html" type="string" brief="HTML-текст" />
///     </args>
///     <body>
	static function html_clear($html) {
		$s = strip_tags($html,'<img>');
		$s = str_ireplace('&nbsp;','',$s);
		$s = trim($s);
		return $s;
	}
///     </body>
///   </method>


///   <method scope="class" name="rmdir">
///     <brief>Удаляет каталог рекурсивно вместе со всеми его подкаталогами и содержащимися в нем файлами</brief>
///     <args>
///       <arg name="dir" type="string" />
///     </args>
///     <body>
	static function rmdir($dir) {
		$fo = IO_FS::file_object_for($dir);
		if ($fo) $fo->rm();
	}
///     </body>
///   </method>

///   <method scope="class" name="copy">
///     <brief>Копирует рекурсивно вместе со всеми подкаталогами и содержащимися в нем файлами</brief>
///     <args>
///       <arg name="from" type="string" />
///       <arg name="to" type="string" />
///     </args>
///     <body>
	static function copy($from,$to) {
		IO_FS::cp($from, $to);
	}
///     </body>
///   </method>


///   <method scope="class" name="chmod_file">
///     <brief>Устанавливает права на файл, созданный движком</brief>
///     <args>
///       <arg name="name" type="string" />
///     </args>
///     <body>
	static function chmod_file($name) {
	  return IO_FS::file_object_for($name)->set_permission();
	}
///     </body>
///   </method>

///   <method scope="class" name="chmod_dir">
///     <brief>Устанавливает права на каталог, созданный движком</brief>
///     <args>
///       <arg name="name" type="string" />
///     </args>
///     <body>
	static function chmod_dir($name) {
	  return IO_FS::file_object_for($name)->set_permission();
	}
///     </body>
///   </method>


///   <method scope="class" name="mkdirs">
///     <brief>Создает каталог рекурсивно</brief>
///     <args>
///       <arg name="dir" type="string" />
///     </args>
///     <body>
	public function mkdirs($dirs) {
	  return IO_FS::Dir($dirs)->create();
	}
///     </body>
///   </method>


//FIXME: НЛО прилетео и написало это:
	static $ifs_cache = array();


///   <method scope="class" name="items_for_select" returns="array">
///     <brief>Создает массив вида ключ=>значение на основании переданного источника</brief>
///     <args>
///       <arg name="source" type="string|iterable" />
///     </args>
///     <body>


	static function items_for_select($s) {
		if (is_string($s)) {
			if (isset(self::$ifs_cache[$s])) return self::$ifs_cache[$s];
		}
		$rc = self::items_for_select_generate($s);
		if (is_string($s)) {
			self::$ifs_cache[$s] = $rc;
		}
		return $rc;
	}


	static function items_for_select_generate($s) {

		if (Core_Types::is_callable($s))
			$s = Core::invoke($s);

		if (is_string($s)) if ($m = Core_Regexps::match_with_results('/^:(.+)$/',$s)) {
			$method = trim($m[1]);
			$s = CMS::$current_controller->$method();
		}
		if (Core_Types::is_iterable($s)) {
			$items = array();
			foreach($s as $k => $v) {
				if ($v==''&& (is_string($k) && Core_Regexps::match('/^(var|db|orm)/',$k))) {
					$items += self::items_for_select($k);
				}
				elseif ($v instanceof DB_ORM_Mapper) {
					$items += self::items_for_select($v->select());
				}
				else if ($v instanceof DB_ORM_Entity) {
					$items[$v->id()] = $v;
				}
				else if (is_int($k)&&(Core_Types::is_callable($v) || (is_string($v) && Core_Regexps::match('/^(var|db|orm)/',$v)))) {
					$items += self::items_for_select($v);
				}
				else $items[$k] = $v;
			}
			return $items;
		}

		else if ($m = Core_Regexps::match_with_results('/^var:(.+)$/',$s)) {
			return self::get_var_value($m[1]);
		}

		else if ($m = Core_Regexps::match_with_results('/^orm:(.+)$/',$s)) {
			$items = array();
			foreach(self::orm()->downto($m[1]) as $row) $items[$row->id] = $row;
			return $items;
		}

		else if ($m = Core_Regexps::match_with_results('/^(.+)::(.+)$/',$s)) {
			$class = str_replace('.','_',trim($m[1]));
			$method = trim($m[2]);
			$ref = new ReflectionMethod($class,$method);
			return $ref->invoke(NULL);
		}

		else if ($m = Core_Regexps::match_with_results('/^db:([a-z0-9_]+)(.*)$/i',$s)) {
			$table = $m[1];
			$s = $m[2];
			$value = 'id';
			$title = 'title';
			$query = 'select';
			if ($m = Core_Regexps::match_with_results('/^->([a-z0-9_]+)(.*)$/',$s)) {
				$query = $m[1];
				$s = $m[2];
			}
			if ($m = Core_Regexps::match_with_results('/^\((.+),(.+)\)$/',$s)) {
				$value = $m[1];
				$title = $m[2];
			}
			$rows = DB_SQL::db()->$table->$query->run();
			$items = array();
			foreach($rows as $row) $items[$row->$value] = $row->$title;
			return $items;
		}

		else return array();
	}
///     </body>
///   </method>


///   <method scope="class" name="gallery_sort" returns="array">
///     <brief>Сортирует объекты в галерее в соответствии с установленным порядком</brief>
///     <args>
///       <arg name="source" type="array" />
///     </args>
///     <body>
	static function gallery_sort(&$ar) {
		uasort($ar,array('CMS','gallery_sort_cb'));
		return $ar;
	}
///     </body>
///   </method>

/** --------------------------------------------------------------- */

///   <method scope="class" name="s2date" returns="int">
///     <brief>Переводит дату из строкового представления в timestamp</brief>
///     <details>
///       В качестве параметра принимаются значения вида: "d.m.y", "d.m.y - G:i", "d.m.y - G:i:s".
///       Если передана некорректная строка, то будет возвращен ноль.
///     </details>
///     <args>
///       <arg name="source" type="string" />
///     </args>
///     <body>
	static function s2date($in) {
		return Time_DateTime::s2date($in);
	}
///     </body>
///   </method>


	static function validate_dmy(&$d,&$m,&$y) {
		$d = (int)$d; if ($d>31) $d = 1; if ($d<10) $d = "0$d";
		$m = (int)$m; if ($m>12) $m = 1; if ($m<10) $m = "0$m";
		$y = (int)$y;
		if ($y<=20) $y = 2000+$y;
		if ($y>=21&&$y<100) $y = 1900+$y;
		if ($y<1000) $y = "0$y";
	}

	static function validate_2d($s) {
		$s = trim(Core_Regexps::replace('{[^\d]+}','',$s));
		$s = substr($s,0,2);
		while (strlen($s)<2) $s = "0$s";
		return $s;
	}

///   <method scope="class" name="s2sqldate" returns="string">
///     <brief>Переводит дату из строкового представления в формат SQL DATE</brief>
///     <details>
///       В качестве параметра принимаются значения вида: "d.m.y" или "d.m.Y - G:i" или "d.m.Y - G:i:s".
///       Если передана некорректная строка, то будет возвращено "0000-00-00".
///     </details>
///     <args>
///       <arg name="source" type="string" />
///     </args>
///     <body>
	static function s2sqldate($in) {
		return Time_DateTime::s2sqldate($in);
	}
///     </body>
///   </method>

///   <method scope="class" name="datetime2timestamp" returns="int">
///     <brief>Переводит дату/время в timestamp</brief>
///     <details>
///       Функция, более широкого формата, чем s2date. 
///		  Принимает также дату/время в том виде, в каком они приходят из БД в полях типа DATE, DATETIME.
///       Если передано целочисленное значение, то считается, что это timestamp - 
///		  в этом случае он возвращается в том же виде, в котором получен.
///     </details>
///     <args>
///       <arg name="source" type="string|int" />
///     </args>
///     <body>
	static function datetime2timestamp($time) {
		return Time_DateTime::datetime2timestamp($time);
	}
///     </body>
///   </method>


///   <method scope="class" name="datetime_add" returns="string">
///     <brief>Суммирует дату/время с секундами</brief>
///     <args>
///       <arg name="datetime" type="string" />
///       <arg name="sec" type="int" />
///     </args>
///     <body>
	static function datetime_add($datetime,$sec) {
		return Time_DateTime::datetime_add($datetime,$sec);
	}
///     </body>
///   </method>



///   <method scope="class" name="sqldateformat" returns="string">
///     <brief>Форматирует SQL DATE/DATETIME в соответствии с переданным форматом</brief>
///     <details>
///       В формате допустимы только dmyYHGis
///     </details>
///     <args>
///       <arg name="format" type="string" brief="Формат" />
///       <arg name="time" type="string|int" brief="Дата/время" />
///     </args>
///     <body>
	static function sqldateformat($format,$time) {
		return Time_DateTime::sqldateformat($format,$time);
	}
///     </body>
///   </method>

///   <method scope="class" name="date" returns="string">
///     <brief>Форматирует дату/время в соответствии с переданным форматом</brief>
///     <details>
///       В отличие от стандартной функции PHP принимает дату/время не только в виде timestamp, но и в строковом.
///     </details>
///     <args>
///       <arg name="format" type="string" brief="Формат" />
///       <arg name="time" type="string|int" brief="Дата/время" />
///     </args>
///     <body>
	static function date($format,$time) {
		return self::sqldateformat($format,$time);
	}
///     </body>
///   </method>

///   <method scope="class" name="date_in_human_language" returns="string">
///     <brief>Возвращает дату на "человеческом" языке - строку вида "11 ноября 2011".</brief>
///     <args>
///       <arg name="time" type="string|int" />
///     </args>
///     <body>
	static function date_in_human_language($time) {
		$time = self::datetime2timestamp($time);
		return self::lang()->_common->date_in_human_language($time);
	}
///     </body>
///   </method>

///   <method scope="class" name="today" returns="int">
///     <brief>Возвращает timestamp, соответствующий началу сегодняшних суток</brief>
///     <body>
	static function today() {
		return Time::now()->setTime(0,0,0)->ts;
	}
///     </body>
///   </method>

/** --------------------------------------------------------------- */

///   <method scope="class" name="admin_path" returns="string">
///     <brief>Возвращает URI, приведенный в соответствие с текущим местоположением админа</brief>
///     <details>
///       Адреса админских механизмов сайта могут определяться как по REQUEST_URI (например /admin/*), так и по домену (например, admin.mysite.zone/*)
///	      Данный метод поможет создать маппер, который без модификаций будет работать в любом варианте.
///       CMS::admin_path('banners/templates') может вернуть как '/admin/banners/templates', так и '/banners/templates' - в зависимости от того, где у нас работает админ.
///     </details>
///     <args>
///       <arg name="path" type="string" />
///     </args>
///     <body>
	static function admin_path($path='') {
		$path  = trim(trim($path,'/'));
		$admin = CMS_Admin::path();
		if ($path=='') {
			if ($admin=='') return '/';
			return "/$admin/";
		}
		return "/$admin/$path/";
	}
///     </body>
///   </method>


///   <method scope="class" name="is_admin_request" returns="boolean">
///     <brief>Определяет, является ли данный HTTP-запрос запросом к админу</brief>
///     <details>
///       Наряду с функцией CMS::admin_path() позволяет создать маппер, независимый от положения админа.
///     </details>
///     <args>
///       <arg name="request" type="WebKit.HTTP.Request" />
///     </args>
///     <body>
	static function is_admin_request($request) {
		if (CMS_Admin::$host&&CMS_Admin::$host!=$request->host) return false;
		if (strpos($request->urn,self::admin_path())===0) return true;
		return false;
	}
///     </body>
///   </method>

///   <method scope="class" name="check_password" returns="boolean">
///     <args>
///       <arg name="hash" type="string" />
///       <arg name="password" type="string" />
///     </args>
///     <body>
	static function check_password($hash,$password) {
		if (md5($password)==$hash) return true;
		return false;
	}
///     </body>
///   </method>


///   <method name="views_path">
///     <args>
///       <arg name="path" type="string|false" default="false" />
///     </args>
///     <body>
	static function views_path($path=false) {
		return Templates::get_path($path);
		// return self::$views_path.($path?"/$path":"");
	}
///     </body>
///   </method>


///   <method name="app_path">
///     <args>
///       <arg name="path" type="string|false" default="false" />
///     </args>
///     <body>
	static function app_path($path=false) {
		return self::$app_path.($path?"/$path":"");
	}
///     </body>
///   </method>


///   <method name="view">
///     <args>
///       <arg name="name" type="string" />
///     </args>
///     <body>
	static function view($name) {
		return Templates::get_path($name);
		// return self::$views_path . "/$name";
	}
///     </body>
///   </method>

///   <method name="app_view">
///     <args>
///       <arg name="name" type="string" />
///     </args>
///     <body>
	static function app_view($name) {
		return self::$app_path . "/views/$name";
	}
///     </body>
///   </method>


///   <method name="stdfile">
///     <args>
///       <arg name="name" type="string" />
///       <arg name="ext" type="string" />
///     </args>
///     <body>
	static function stdfile($name) {
		return self::$assets_dir . "/$name";
	}
///     </body>
///   </method>


///   <method name="stdfile_url">
///     <args>
///       <arg name="name" type="string" />
///       <arg name="ext" type="string" />
///     </args>
///     <body>
	static function stdfile_url($name) {
		return "/".self::$assets_dir . "/$name";
	}
///     </body>
///   </method>

///   <method name="stdstyle">
///     <args>
///       <arg name="name" type="string" />
///     </args>
///     <body>
	static function stdstyle($name,$replace=false) {
		if (isset(self::$i_stdstyles[$name])) return ''; self::$i_stdstyles[$name] = true;
		$url = self::stdfile_url("styles/$name");
		return "<link rel=\"stylesheet\" type=\"text/css\" href=\"$url\" />";

	}
///     </body>
///   </method>

///   <method name="stdscript">
///     <args>
///       <arg name="name" type="string" />
///     </args>
///     <body>
	static function stdscript($name,$replace=false) {
		if (isset(self::$i_stdscripts[$name])) return ''; self::$i_stdscripts[$name] = true;
		$url = self::stdfile_url("scripts/$name");
		return "<script type=\"text/javascript\" src=\"$url\"></script>";

	}
///     </body>
///   </method>

///   <method name="file_url" returns="string">
///     <args>
///       <arg name="filename" type="string" />
///     </args>
///     <body>
	static function file_url($file) {
		if ($m = Core_Regexps::match_with_results('{^\./(.+)$}',$file)) {
			return '/'.$m[1];
		}
		return '#';
	}
///     </body>
///   </method>


///   <method name="render_mail" returns="string">
///     <args>
///       <arg name="tpl" type="string" />
///       <arg name="parms" type="array" />
///       <arg name="layout" type="string" default="mail" />
///     </args>
///     <body>
	static function render_mail($tpl,$parms,$layout='mail') { return self::render($tpl,$parms,$layout); }
///     </body>
///   </method>


///  </protocol>








///  <protocol name="supporting">


///   <method scope="class" name="process_navigation">
///     <args>
///       <arg name="uri" type="string|false" default="false" />
///     </args>
///     <body>
	static function process_navigation($uri=false) {
		if (!empty(self::$navigation)) return self::$navigation;
		if (!$uri) $uri = WS::env()->request->path;
		Core::load(self::$nav_module);
		self::$navigation = Core::make(self::$nav_module);
		self::$navigation->process($uri);

	}
///     </body>
///   </method>

///   <method scope="class" name="check_globals_or" returns="boolean">
///     <args>
///       <arg name="vars" type="string" />
///     </args>
///     <body>
	static function check_globals_or($s) {
		if (self::$globals['full']) return true;
		Core::load('Text');
		foreach(Text::Tokenizer($s,',') as $item) {
			$item = trim($item);
			if ($item!='') {
				if (self::$globals[$item]) return true;
			}
		}
		return false;
	}
///     </body>
///   </method>


///   <method scope="class" name="abs_refs_cb" returns="string">
///     <args>
///       <arg name="matches" type="array" />
///     </args>
///     <body>
	static function abs_refs_cb($m) {
		$s = $m[1];
		$ref = trim($m[2]);
		if (!Core_Regexps::match('{^http://}',$ref)) {
			$ref = ltrim($ref,'.');
			$ref = ltrim($ref,'/');
			$ref = "http://".CMS::host().'/'.$ref;
		}
		return "<a$s"."href=\"$ref\"";
	}
///     </body>
///   </method>


///   <method scope="class" name="get_var_value" returns="mixed">
///     <args>
///       <arg name="name" type="string" />
///     </args>
///     <body>
	static function get_var_value($name) {
		$site = false;
		if ($m = Core_Regexps::match_with_results('{^(.+)/([^/]+)$}',$name)) {
			$name = trim($m[1]);
			$site = trim($m[2]);
			if ($site=='*') $site = CMS_Admin::site();
		}
		return CMS::vars()->get($name,$site);
	}
///     </body>
///   </method>

///   <method scope="class" name="gallery_sort_cb" returns="int">
///     <args>
///       <arg name="arg1" type="array" />
///       <arg name="arg2" type="array" />
///     </args>
///     <body>
	static function gallery_sort_cb($a,$b) {
		if ($a['ord']>$b['ord']) return 1;
		if ($a['ord']<$b['ord']) return -1;
		return 0;
	}
///     </body>
///   </method>

///   <method scope="class" name="layout_view" returns="WebKit.Views.TemplateView|false">
///     <args>
///       <arg name="view" type="WebKit.Views.TemplateView|false" default="false" />
///     </args>
///     <body>
	static function layout_view($view=false) {
		if ($view) self::$i_view = $view;
		return self::$i_view;
	}
///     </body>
///   </method>

///   <method scope="class" name="use_styles" varargs="true">
///     <body>
	static function use_styles() {
		$args = func_get_args();
		foreach($args as $arg) self::$i_view->use_styles($arg);
	}
///     </body>
///   </method>


///   <method scope="class" name="use_helper">
///     <body>
	static function use_helper($name,$helper) {
		Templates_HTML::use_helper($name,$helper);
	}
///     </body>
///   </method>

///   <method scope="class" name="field_type">
///     <body>
	static function field_type($name,$module) {
		self::$fields_types[$name] = $module;
	}
///     </body>
///   </method>


///   <method scope="class" name="use_scripts" varargs="true">
///     <body>
	static function use_scripts() {
		$args = func_get_args();
		foreach($args as $arg) self::$i_view->use_scripts($arg);
	}
///     </body>
///   </method>

///   <method scope="class" name="host" returns="string">
///     <body>
	static function host() {
		if (self::$host) return self::$host;
		return $_SERVER['HTTP_HOST'];
	}
///     </body>
///   </method>

///   <method scope="class" name="user_login" returns="string">
///     <body>
	static function user_login() {
		$headers = getAllHeaders();
		$auth = trim($headers['Authorization']);
		if ($m = Core_Regexps::match_with_results('{^Basic\s+(.+)$}',$auth)) {
			$auth = Core_Strings::decode64($m[1]);
			if ($m = Core_Regexps::match_with_results('{^([^:]+):}',$auth)) {
				$login = trim($m[1]);
				if ($login!='') return $login;
			}
		}
		return false;
	}
///     </body>
///   </method>


///  </protocol>








///   <protocol name="obsolete">

///   <method scope="class" name="cgf" returns="array">
///     <body>
	static function cfg() { return self::$cfg; }
///     </body>
///   </method>

///   <method scope="class" name="page2mail" returns="Mail.Message">
///     <args>
///       <arg name="page" type="Component.Pages.Entity|string" />
///       <arg name="parms" type="array" default="array()" />
///       <arg name="layout" type="string" default="mail" />
///     </args>
///     <body>
	static function page2mail($page,$parms=array(),$layout='mail') {
		if (!is_object($page)) $page = Component_Pages_Entity::find_by_url($page);
		if (!$page) return false;
		if (!$page->isactive) return false;
		Core::load('Mail.Message');
		$mail = Mail::Message()->content_type('text/html; charset=utf-8');
		$subject = trim($page->parms['subject']); if ($subject!='') $mail->subject($subject);
		$from = trim($page->parms['from']); if ($from!='') $mail->from($from);
		$content = $page->content;
		foreach($parms as $parm => $value) $content = str_replace("%{".$parm."}",$value,$content);
		$body = self::render_mail('empty',array('content'=>$content));
		$body = self::abs_refs($body);
		$mail->html($body);
		return $mail;
	}
///     </body>
///   </method>


///   </protocol>


}
/// </class>


class CMS_PlugObject {

	public function __get($name) {
		return false;
	}

	public function __set($name,$value) {
		return false;
	}

	public function __call($name,$args) {
		return false;
	}

}

class CMS_ObjectsBuilder {

	public function __get($name) {

		if (isset(CMS::$created_objects[$name])) {
			return CMS::$created_objects[$name];
		}

		$class = 'CMS_PlugObject';

		if (isset(CMS::$registered_objects[$name])) {
			$module = trim(CMS::$registered_objects[$name]);
			Core::load($module);
			$class = str_replace('.','_',$module);
		}

		CMS::$created_objects[$name] = new $class;
		return CMS::$created_objects[$name];
	}

}


class CMS_Exception extends Exception {}


/// </module>
