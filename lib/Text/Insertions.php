<?php

Core::load('WS', 'Templates.HTML');

class Text_Insertions implements Core_ConfigurableModuleInterface {
  const VERSION = '0.1.0';
  
  static protected $filters = array();
  
  static protected $options = array('filter_class' => 'Text.Insertions.Filter');
  
  static public function initialize(array $options = array()) {
    self::options($options);
  }
  
  static public function options(array $options = array()) {
    if (count($options)) Core_Arrays::update(self::$options, $options);
    return self::$options;
  }
  
  static public function option($name, $value = null) {
    if (is_null($value)) return self::$options[$name];
    return self::$optios[$name] = $value;
  }
  
  static public function filter($name = 'default', $use_fallback_views = true) {
    return isset(self::$filters[$name]) && self::$filters[$name] instanceof Text_Insertions_FilterInterface ?
      self::$filters[$name] : self::$filters[$name] = Core::make(self::$options['filter_class'])->use_fallback_views($use_fallback_views);
  }
  
  static public function register_filter() {
    $args = func_get_args();
    return call_user_func_array(array(self::filter(), 'register'), $args);
  }
  
  static public function remove_filter($name) {
    return self::filter()->remove($name);
  }
}

interface Text_Insertions_FilterInterface {
//TODO: property or index interface
  public function register();
  public function remove($name);
  public function get($name);
  public function exists($name);
  public function process($content);

}

class Text_Insertions_Filter implements Text_Insertions_FilterInterface {

  protected $register = array();
  protected $views_paths = array();
  protected $use_fallback_views = true;
  protected $views_folder = 'insertions';
  protected $args = array();

  public function use_fallback_views($v=true)
  {
    $this->use_fallback_views = $v;
    return $this;
  }

  public function set_views_folder($folder)
  {
    $this->views_folder = trim($folder, '/');
    return $this;
  }

  public function get_views_folder()
  {
    return $this->views_folder;
  }
  
  protected function standartizate_name($name) {
    return strtolower($name);
  }
  
  public function register() {
		$args = func_get_args();
		foreach (Core::normalize_args($args) as $name => $call) {
		  if ($call instanceof Core_InvokeInterface) $this->register[$this->standartizate_name($name)] = $call;
		}
		return $this;
  }
  
  public function remove($name) {
    unset($this->register[$this->standartizate_name($name)]);
    return $this;
  }
  
  public function get($name) {
    return $this->register[$this->standartizate_name($name)];
  }
  
  public function exists($name) {
    return isset($this->register[$this->standartizate_name($name)]);
  }
  
  public function process($content, $args = array()) {
    $this->args = $args;
    $result = preg_replace_callback($this->get_pattern(), array($this,'replace_callback'), $content);
    $this->args = array();
    return $result;
  }
  
  protected function replace_callback($m) {
    $name = $this->standartizate_name($m[1]);
    $parms = $m[2];
    $res = false;
    Events::call("cms.insertions.$name",$parms,$res);
    if (is_string($res)) return $res;
    Events::call("%{$name}{{$parms}}",$res);
    if (is_string($res)) return $res;
    Events::call("%{$m[1]}{{$parms}}",$res);
    if (is_string($res)) return $res;
    if (isset($this->register[$name])) {
      return $this->invoke($name, $parms);
    } else if ($this->use_fallback_views) {
      $res = $this->fallback($name, $parms);
      if ($res) {
        return $res;
      }
    }
    return "%{$m[1]}{{$m[2]}}";
  }

  public function get_views_paths()
  {
    return $this->views_paths;
  }

  public function add_views_paths($paths)
  {
    if (!is_array($paths)) {
      $paths = array($paths);
    }
    $this->views_paths = array_merge($this->views_paths, $paths);
    return $this;
  }

  public function set_views_paths($paths)
  {
    $this->views_paths = $paths;
    return $this;
  }

  protected function cache()
  {
    return WS::env()->cache;
  }

  protected function cache_key($name = 'paths')
  {
    return 'tao:insertions:'.$name;
  }

  protected function search_fallback_template($name)
  {
    $template = false;
    $cpaths = $this->cache()->get($this->cache_key());
    if (!is_array($cpaths)) {
      $cpaths = array();
    }
    if (isset($cpaths[$name])) {
      $template = $cpaths[$name];
    } else {
      $cpaths[$name] = false;
      foreach ($this->views_paths as $path) {
        $path = $path. '/' . $this->views_folder . '/' . $name;
        $path = trim($path, '/');
        $t = Templates::HTML($path);
        if ($t->exists()) {
          $template = $path;
          $cpaths[$name] = $path;
          break;
        }
      }
      $this->cache()->set($this->cache_key(), $cpaths, 0);
    }
    return $template;
  }

  protected function fallback($name, $parms)
  {
    $name = trim($name, '/');
    $this->views_paths[] = '';
    $template = $this->search_fallback_template($name);
    if ($template) {
      $args = array(
        'env' => WS::env(),
        'request' => WS::env()->request,
        'parms' => $parms
      );
      if (isset($this->args['layout'])) {
        return $this->args['layout']->partial($template, $args);
      }
      return Templates::HTML($template)->with($args)->render();
    }
    return null;
  }
  
  //TODO: смешивать параметры
  protected function invoke($name, $parms) {
    return $this->register[$name]->invoke($parms);
  }
  
  protected function get_pattern() {
    return '/%([a-z0-9_-]+)\{([^\{\}]*)\}/i';
  }

}
