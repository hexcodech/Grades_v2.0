<?php

if(isset($_GET['id']){
	if(isset($_GET['action']){
		if($_GET['action']=='settings'){
			if($is_get){
				//get all possible settings
				
			}else if($is_put){
				//update settings
			}
		}	 
	}else{
		//get notification informations
	}
}else{
	if($is_post){
		//create new notification
		
	}else if($is_delete){
		if(isset($_DELETE['notification_ids']){
			//delete notification
		}
	}else if($is_get){
		//list all notifications [filters]
	}
}
?>