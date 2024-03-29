<?php
	
if(isset($_GET['id'])){
	
	$event_type_id = -1;
	
	$data=$db->doQueryWithArgs("SELECT type_id FROM events WHERE id=?", array($_GET['id']), "i");
	if(count($data) == 1){
		$event_type_id = $data[0]['type_id'];
	}
	
	if(isset($_GET['action'])){
		if($_GET['action']=='settings'){
			if($is_get){
				//get all possible settings
				$event_options=getEventOptions($event_type_id, $_GET['id']);
				$JSON["settings"]=$event_options;
				
			}else if($is_put){
				//update settings
				
				global $db;
				
				//get group this event is in
				$data = $db->doQueryWithArgs("SELECT group_id FROM group_relations WHERE member_id=? AND member_type=3", array($_GET['id']), "i");
				if(count($data)==1){
					$group_id = $data[0]["group_id"];
					
					if(currentUserCan("manage_options", $group_id)){
						
						if(isset($_PUT['option_key'])&&isset($_PUT['value'])){
							$global_options = array(
								'title' => 'text'
							);
							
							if(in_array($_PUT['option_key'], array_keys($global_options))){
								$validation = validateDynamicInput($_PUT['value'], $global_options[$_PUT['option_key']]);
								$_PUT['value']=$validation[1];
								
								if($validation[0]){
									$db->doQueryWithArgs("REPLACE into events (id,".$_PUT['option_key'].") values(?, ?)", array($_GET['id'], $_PUT['value']), "is");
								}else{
									addError(getMessages()->ERROR_API_INVALID_INPUT);
								}
							}else{
								$data=$db->doQueryWithArgs("SELECT id,input_data_type FROM event_type_options WHERE option_key=? AND event_type_id=?", array($_PUT['option_key'], $event_type_id), "si");
								
								if(count($data)==1){
									
									$id=$data[0]["id"];
									
									$validation = validateDynamicInput($_PUT['value'], $data[0]["input_data_type"]);
									$_PUT['value']=$validation[1];
									
									if($validation[0]){
										
										$check = $db->doQueryWithArgs("SELECT COUNT(*) as count FROM event_options WHERE event_id=? AND event_type_option_id=?", array($_GET['id'], $id), "ii");
										
										if($check[0]['count'] > 0){
											$db->doQueryWithArgs("UPDATE event_options SET value=? WHERE event_id=? AND event_type_option_id=?", array($_PUT['value'], $_GET['id'], $id), "sii");
										}else{
											$db->doQueryWithArgs("INSERT INTO event_options (event_id,event_type_option_id,value) VALUES(?, ?, ?)", array($_GET['id'], $id, $_PUT['value']), "iis");
										}
										
										
									}else{
										addError(getMessages()->ERROR_API_INVALID_INPUT);
									}
								}else{
									addError(getMessages()->ERROR_API_INVALID_INPUT);
								}
							}
						}else{
							addError(getMessages()->ERROR_API_REQUIRED_FIELDS);
						}
					}else{
						addError(getMessages()->ERROR_API_PRIVILEGES);
					}
				}else{
					addError(getMessages()->UNKNOWN_ERROR(6));
				}
			}else{
				addError(getMessages()->ERROR_API_REQUIRED_FIELDS);
			}
		}else{
			addError(getMessages()->ERROR_API_REQUIRED_FIELDS);
		}
	}else{
		//get event informations if member of group
		global $db;
		
		
		$data = $db->doQueryWithArgs("SELECT group_id FROM group_relations WHERE member_id=? AND member_type=3", array($_GET['id']), "i");
		if(count($data)==1){
			
			$event = $db->doQueryWithArgs("SELECT events.id, events.title, events.type_id, event_types.title as event_type FROM events LEFT JOIN event_types ON events.type_id=event_types.id WHERE events.id=?", array($_GET['id']), "i")[0];
			
			$event['options'] = getEventOptions($event['type_id'], $event['id'], false);
			
			$data = $db->doQueryWithArgs("SELECT group_id FROM group_relations WHERE member_id=? AND member_type=3", array($event['id']), "i");

			$event['can_edit'] = (count($data) > 0) ? (currentUserCan('manage_members', $data[0]["group_id"])) : false;
			
			if($event['type_id'] == 2/*lesson*/){
				$data = $db->doQueryWithArgs("SELECT events.*,event_types.title as type FROM event_options LEFT JOIN event_type_options ON event_options.event_type_option_id=event_type_options.id LEFT JOIN events ON event_options.event_id=events.id LEFT JOIN event_types ON events.type_id=event_types.id WHERE event_type_options.option_key='lesson_id' AND event_options.value=?", array($event['id']), "i");
				
				$events = array();
				
				foreach($data as $e){
					
					$e['options'] = getEventOptions($e['type_id'], $e['id'], false);
					$g_data = $db->doQueryWithArgs("SELECT group_id FROM group_relations WHERE member_id=? AND member_type=3", array($e['id']), "i");
					
					$e['can_edit'] = (count($g_data) > 0) ? (currentUserCan('manage_members', $g_data[0]["group_id"])) : false;
					
					if($e['type']=='test'){
						
						$grade = $db->doQueryWithArgs("SELECT id, grade FROM grades WHERE user_id=? AND event_id=?", array(getUser()['id'],$e['id']), "ii");
					
						if(count($grade) > 0){
							$e['grade'] = $grade[0]['grade'];
							$e['grade_id'] = $grade[0]['id'];
						}else{
							$e['grade'] = '';
							$e['grade_id'] = -1;
						}
						
					}
					
					$events[] = $e;
				}
				
				$event['events'] = $events;
				
			}
			
			$JSON['event'] = $event;
			
		}else{
			addError(getMessages()->UNKNOWN_ERROR(7));
		}
	}
}else{
	if($is_post){
		//create new event
		
		if(isset($_POST['event_title']) && isset($_POST['event_type_id']) && isset($_POST['parent_group_id']) && isset($_POST['options'])){
			if(currentUserCan("manage_members", $_POST['parent_group_id'])){
				if(is_array($_POST['options'])){
					global $db;
					$data = $db->doQueryWithArgs("SELECT id, option_key, input_data_type FROM event_type_options WHERE required = 1 AND event_type_id=?", array($_POST['event_type_id']), "i");
					
					$all_fields = true;
					
					$options = array();
					
					foreach($data as $val){
						
						if($all_fields && array_key_exists($val['option_key'], $_POST['options'])){
							$validation = validateDynamicInput($_POST['options'][$val['option_key']], $val['input_data_type']);
							if($validation[0]){
								$options[$val['id']] = $validation[1];
							}else{
								$all_fields=false;
								var_dump($val);
								addError(getMessages()->ERROR_API_INVALID_INPUT);
							}
						}else{
							$all_fields=false;
						}
					}
					
					if($all_fields){
						
						//get next auto-increament id
						
						$data = $db->doQueryWithoutArgs("SHOW TABLE STATUS LIKE 'events'");
						$id = $data[0]['Auto_increment'];
						
						$JSON['event']['id'] = $id;
						
						$db->doQueryWithArgs("INSERT INTO events(id, title,type_id) VALUES(?,?,?)", array($id, $_POST['event_title'], $_POST['event_type_id']), "isi");
						
						$qm=array();
						$values=array();
						$types="";
						foreach($options as $event_type_option_id => $option){
							$qm[]="(?,?,?)";
							$values[]=$id;
							$values[]=$event_type_option_id;
							$values[]=$option;
							$types.="iis";
						}
						
						$db->doQueryWithArgs(("INSERT INTO event_options(event_id,event_type_option_id,value) VALUES ".implode(", ", $qm).""), $values, $types);
						
						//set parent group
						
						$db->doQueryWithArgs("INSERT INTO group_relations(member_id,group_id,member_type) VALUES(?,?,3)", array($id,$_POST['parent_group_id']), "ii");
						
						
						
					}else{
						addError(getMessages()->ERROR_API_REQUIRED_FIELDS);
					}
					
				}else{
					addError(getMessages()->ERROR_API_INVALID_INPUT);
				}
			}else{
				addError(getMessages()->ERROR_API_PRIVILEGES);
			}
		}else{
			addError(getMessages()->ERROR_API_REQUIRED_FIELDS);
		}
		
	}else if($is_delete){
		if(isset($_DELETE['event_id'])){
			//delete event
			
			//get parent group id
			
			global $db;
			$data = $db->doQueryWithArgs("SELECT group_id FROM group_relations WHERE member_id=? AND member_type=3", array($_DELETE['event_id']), "i");
			if(count($data)==1){
				if(currentUserCan("manage_options", $data[0]["group_id"])){
					deleteEvent($_DELETE['event_id']);
				}else{
					addError(getMessages()->ERROR_API_PRIVILEGES);
				}
			}else{
				addError(getMessages()->ERROR_API_INVALID_INPUT);
			}
		}
	}else if($is_get){
		//list all events [filters]
		
		global $db;
		
		
		$query="SELECT events.id,events.title,events.type_id, event_types.title as event_type, group_relations.group_id as parent_group_id FROM events LEFT JOIN event_types ON events.type_id=event_types.id LEFT JOIN group_relations ON events.id=group_relations.member_id WHERE group_relations.member_type=3 AND 1=?";
		
		$events=array();
		$args=array(1);
		$types="i";
		
		$filters=getFilters();
		
		if($filters!=false){
			
			if(isset($filters['type']) && $filters['type'] == 'settings' && isset($filters['event_type_id'])){
				
				$event_options=getEventOptions($filters['event_type_id']);
				$JSON['settings'] = $event_options;
				
			}else{
				
				if(isset($filters['title'])){
					$query.=" AND events.title LIKE ?";
					$args[]="%".$filters['title']."%";
					$types.="s";
				}
				if(isset($filters['search'])){
					$query.=" AND events.title LIKE ?";
					$args[]=$filters['search']."%";
					$types.="s";
				}
				if(isset($filters['event_type_id'])){
					$query.=" AND events.type_id = ?";
					$args[]=$filters['event_type_id'];
					$types.="s";
				}
				if(isset($filters['event_type'])){
					$query.=" AND event_types.title = ?";
					$args[]=$filters['event_type'];
					$types.="s";
				}
				if(isset($filters['items_per_page']) && isset($filters['page'])){
					$query.=" ORDER BY events. ?,?";
					$args[]=(((int)$filters['items_per_page']) * ((int)$filters['page']));
					$args[]=$filters['items_per_page'];
					$types.="ii";
				}
			}
			
		}
		
		if(!isset($JSON['settings'])){
			
			$events=$db->doQueryWithArgs($query, $args, $types);
				
			if(isset($filters['group_id'])){
				
				if(isInGroup($filters['group_id'], 1, getUser()['id'])){
					
					$parents = getParentGroups(2, $filters['group_id']);
					$parents[] = $filters['group_id'];
					
					foreach($events as $key => $event){
						
						if(!in_array($event['parent_group_id'], $parents)){
							unset($events[$key]);
						}
						
					}
					
				}else{
					$events = array();
					addError(getMessages()->ERROR_API_PRIVILEGES);
				}
				
				$events = array_values($events);
				
			}else{
				
				$parents = getParentGroups(1, getUser()['id']);
				foreach($events as $key => $event){
					if(!in_array($event['parent_group_id'], $parents)){
						unset($events[$key]);
					}
				}
				
			}
			
			//again 'if' outside for more efficency
			
			if(isset($filters['future_only'])){
				
				$now = new DateTime();
				$now->setTime(0,0,0);
				$now = $now->getTimestamp();
				
				foreach($events as $key => $event){
					$events[$key]['options'] = getEventOptions($event['type_id'], $event['id'], false);
					
					if(isset($events[$key]['options']['time_from']) && $event['event_type']!='lesson' && $now > $events[$key]['options']['time_from']){
						unset($events[$key]);
						continue;
					}
					
					if(isset($events[$key]['options']['date']) && $now > $events[$key]['options']['date']){
						unset($events[$key]);
						continue;
					}
					
					$data = $db->doQueryWithArgs("SELECT group_id FROM group_relations WHERE member_id=? AND member_type=3", array($event['id']), "i");
		
					$events[$key]['can_edit'] = (count($data) > 0) ? (currentUserCan('manage_members', $data[0]['group_id'])) : false;
					
				}
				
			}else{
				
				foreach($events as $key => $event){
					$events[$key]['options'] = getEventOptions($event['type_id'], $event['id'], false);
					$data = $db->doQueryWithArgs("SELECT group_id FROM group_relations WHERE member_id=? AND member_type=3", array($event['id']), "i");
		
					$events[$key]['can_edit'] = (count($data) > 0) ? (currentUserCan('manage_members', $data[0]['group_id'])) : false;
					
				}
				
			}
			
			if(isset($filters['order_by_date']) && in_array($filters['order_by_date'], array('ASC','DESC'))){
				
				function cmp_date($a, $b, $multiplier){
					$a_c = isset($a['options']['time_from']) ? $a['options']['time_from'] : (isset($a['options']['date']) ? $a['options']['date'] : (-10 * $multiplier));
					$b_c = isset($b['options']['time_from']) ? $b['options']['time_from'] : (isset($b['options']['date']) ? $b['options']['date'] : (-10 * $multiplier));
					
					return $a_c == $b_c ? 0 : ($a_c > $b_c ? (1 * $multiplier) : (-1 * $multiplier));
					
				}
				
				function cmp_date_asc($a, $b){
				    return cmp_date($a, $b, 1);
				}
				
				function cmp_date_desc($a, $b){
				    return cmp_date($a, $b, -1);
				}
				
				if($filters['order_by_date'] == 'ASC'){
					usort($events, 'cmp_date_asc');
				}else{
					usort($events, 'cmp_date_desc');
				}
				
				
			}
			
			$JSON["events"]=$events;
			
		}
		
	}else{
		addError(getMessages()->ERROR_API_REQUIRED_FIELDS);
	}
}

function getEventOptions($event_type_id, $event_id = null, $fields = true){
	
	global $db;
	
	$event_options = array();
	
	$value = !is_null($event_id);
	
	$data = $db->doQueryWithArgs("SELECT * FROM event_type_options WHERE event_type_id=? ORDER BY id ASC", array($event_type_id), "i");
	
	$e_name = "";
	
	$values = array();
	
	if($value){
		$v_data = $db->doQueryWithArgs("SELECT events.title, event_options.value, event_type_options.option_key FROM events LEFT JOIN event_type_options ON events.type_id=event_type_options.event_type_id LEFT JOIN event_options ON event_type_options.id=event_options.event_type_option_id WHERE events.id=? AND event_options.event_id=? ORDER BY event_type_options.id ASC", array($event_id,$event_id), "ii");
		
		if(count($v_data)>0){
			
			foreach($v_data as $v){
				$values[$v['option_key']] = $v['value'];
			}
			
			$e_name = $v_data[0]['title'];
			
		}
			
		unset($v_data);
	}
	
	$event_options[0]=array(
		'key' => 'title',
		'input_data_type' => 'text',
		'options' => json_decode('{"input_type":"textfield"}'),
		'description'=>getMessages()->EVENT_OPTIONS_TITLE_DESC
	);
	
	if($value){
		$event_options[0]['value'] = $e_name;
	}
	
	if($fields){
		for($i=0;$i<count($data);$i++){
		
			$event_option = $data[$i];
			
			$event_options[]=array(
				'key'=>$event_option["option_key"],
				'input_data_type'=>$event_option["input_data_type"],
				'required'=>$event_option["required"],
				'options'=>translateOptions($event_option["options"]),
				'description'=>getMessages()->$event_option["description_translation_key"]
			);
			
			if($value){
				$event_options[($i + 1)]['value'] = isset($values[$event_option["option_key"]]) ? $values[$event_option["option_key"]] : null;
			}
			
		}
	}else{
		$event_options = array();
		
		for($i=0;$i<count($data);$i++){
		
			$event_option = $data[$i];
			
			$event_options[$event_option["option_key"]] = isset($values[$event_option["option_key"]]) ? $values[$event_option["option_key"]] : null;
			
		}
	}
	
	return $event_options;
}
	
?>