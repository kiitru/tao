<?php

class CMS_Fields_Types_Tags extends CMS_Fields_AbstractField implements Core_ModuleInterface {

	static $cache = array();

	public static function table_tags($table,$name)
	{
		$sfx = $name=='tags'?$name:"{$name}_tags";
		return "{$table}_{$sfx}";
	}
	
	public function cloud($table,$name,$parms=array())
	{
		if (isset($parms['cache'])) {
			$tags = WS::env()->cache->get($parms['cache']);
			if (is_array($tags)) {
				return $tags;
			}
		}
		$table_tags = self::table_tags($table,$name);
		$table_rels = self::table_rels($table,$name);
		$tags = array();
		$cursor = CMS::orm()->connection
			->prepare("SELECT tags.id id,tags.title title, count(rels.item_id) cnt FROM {$table_rels} rels LEFT JOIN {$table_tags} tags ON tags.id=rels.tag_id GROUP BY tags.id ORDER BY tags.title")
			->execute();
		foreach($cursor as $row) {
			$url = false;
			if (isset($parms['urls'])) {
				$url = call_user_func($parms['urls'],$row['id']);
			}
			$tags[$row['id']] = array(
				'id' => $row['id'],
				'title' => $row['title'],
				'count' => $row['cnt'],
				'url' => $url,
			);
		}
		if (isset($parms['cache'])) {
			WS::env()->cache->set($parms['cache'],$tags);
		}
		return $tags;
	}
	
	public function render_cloud($table,$name,$parms=array())
	{
		$tags = '';
		foreach($this->cloud($table,$name,$parms) as $id => $data) {
			$tag = $data['title'];
			$url = $data['url'];
			if ($url) {
				$tag = "<a href=\"{$url}\">{$tag}</a>";
			}
			$tags .= "<li class=\"tag tag-{$id}\">{$tag}</li> ";
		}
		if ($tags=='') {
			return false;
		}
		$cl = "tags tags-{$table} tags-{$table}-{$name}";
		if (isset($parms['class'])) {
			$class = trim($parms['class']);
			$cl .= " {$class}";
		}
		$out = "<ul class=\"{$cl}\">{$tags}</ul>";
		return $out;
	}

	public static function tag_id($title,$table,$add=false)
	{
		$row = CMS::orm()->connection
			->prepare("SELECT * FROM {$table} WHERE title=:title LIMIT 1")
			->bind(array('title' => $title))
			->execute()
			->fetch();
		if ($row) {
			return $row['id'];
		}
		if (!$add) {
			return false;
		}
		CMS::orm()->connection
			->prepare("INSERT INTO {$table} SET title=:title")
			->bind(array('title' => $title))
			->execute();
		return CMS::orm()->connection->last_insert_id();
	}

	public static function tag_title($id,$table)
	{
		$row = CMS::orm()->connection
			->prepare("SELECT * FROM {$table} WHERE id=:id")
			->bind(array('id' => $id))
			->execute()
			->fetch();
		if ($row) {
			return $row['title'];
		}
		return false;
	}


	public static function get_tags($table,$name,$id)
	{
		$cn = "$table-$name-$id";
		if (isset(self::$cache[$cn])) {
			return self::$cache[$cn];
		}
		$table_tags = self::table_tags($table,$name);
		$table_rels = self::table_rels($table,$name);
		$tags = array();
		$cursor = CMS::orm()->connection
			->prepare("SELECT tags.id id,tags.title title FROM {$table_tags} tags, {$table_rels} rels WHERE rels.item_id=:item_id AND tags.id=rels.tag_id ORDER BY tags.title")
			->bind(array('item_id' => $id))
			->execute();
		foreach($cursor as $row) {
			$tags[$row['id']] = $row['title'];
		}
		self::$cache[$cn] = $tags;
		return $tags;
	}

	public static function table_rels($table,$name)
	{
		return self::table_tags($table,$name)."_rels";
	}

	public function assign_from_object($form,$item,$name,$data)
	{
		$value = '';
		$id = (int)$item->id();
		if ($id>0) {
			if ($item->mapper) {
				$table = $item->mapper->options['table'][0];
				$tags = self::get_tags($table,$name,$id);
				foreach($tags as $tag) {
					if ($value!='') {
						$value .= ', ';
					}
					$value .= $tag;
				}
			}
		}
		$form[$name] = $value;
	}

	public function assign_to_object($form,$item,$name,$data)
	{
		$id = (int)$item->id();
		if ($id==0) {
			return;
		}
		if ($item->mapper) {
			$table = $item->mapper->options['table'][0];
			$table_rels = self::table_rels($table,$name);
			CMS::orm()->connection
				->prepare("DELETE FROM {$table_rels} WHERE item_id=:item_id")
				->bind(array('item_id' => $id))
				->execute();
			$table_tags = self::table_tags($table,$name);
			$tags = $form[$name];
			foreach(explode(',',$tags) as $tag) {
				$tag = trim($tag);
				if ($tag!='') {
					$tag_id = self::tag_id($tag,$table_tags,true);
					CMS::orm()->connection
						->prepare("INSERT INTO {$table_rels} SET item_id=:item_id, tag_id=:tag_id")
						->bind(array(
							'item_id' => $id,
							'tag_id' => $tag_id,
						))
						->execute();
				}
			}
		}
	}

	public function preprocess($template, $name, $data)
	{
		parent::preprocess($template, $name, $data);
		$parms = $template->parms;
		if (empty($parms['tagparms']['style'])) {
			$template->update_parm('tagparms', array('style' => 'width: 100%;height:50px;'));
		}
		return $template;
	}

	public function process_schema($name,$data,$table)
	{
		$table_tags = self::table_tags($table,$name);
		$fields_tags = array(
			'id' => array(
				'sqltype' => 'serial',
			),
			'title' => array(
				'sqltype' => 'varchar(100) index',
			),
		);
		CMS_Fields::process_schema($table_tags,$fields_tags);

		$table_rels = self::table_rels($table,$name);
		$fields_rels = array(
			'tag_id' => array(
				'sqltype' => 'int index',
			),
			'item_id' => array(
				'sqltype' => 'int index',
			),
		);
		CMS_Fields::process_schema($table_rels,$fields_rels);

	}
}

class CMS_Fields_Types_tags_ValueContainer extends CMS_Fields_ValueContainer {

	public function tags()
	{
		$id = (int)$this->item->id();
		if ($id==0) {
			return array();
		}

		if (!$this->item->mapper) {
			return array();
		}

		$table = $this->item->mapper->options['table'][0];

		return CMS_Fields_Types_Tags::get_tags($table,$this->name,$id);
	}

	public function render($urls = false)
	{
		$out = '';
		foreach($this->tags() as $id => $tag) {
			if ($out!='') {
				$out .= ', ';
			}
			if ($urls) {
				$url = call_user_func($urls,$id);
				$out .= "<a class='tag' href='{$url}'>{$tag}</a>";
			} else {
				$out .= $tag;
			}
		}
		return $out;
	}
}
