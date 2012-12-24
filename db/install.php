<?php

function xmldb_gradingform_btec_install() {
    global $DB;

        
		  $dbman = $DB->get_manager();

    $record = new stdClass();
    $record->courseid       = 0;
    $record->userid			=0;
	$record->name			='BTEC';
	$record->scale			='Refer,Pass,Merit,Distinction';
	$record->description	='No numbers or percentages, a level is only gained if every item at that level and below is gained';
	$record->descriptionformat =1;
	
	$DB->insert_record('scale', $record);
	
    
 
}
