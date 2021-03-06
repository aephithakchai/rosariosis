<?php

//FJ days numbered
if (SchoolInfo('NUMBER_DAYS_ROTATION') !== null)
	include('modules/School_Setup/includes/DayToNumber.inc.php');
	
if(!$_REQUEST['month'])
	$_REQUEST['month'] = date('n');
else
	$_REQUEST['month'] = MonthNWSwitch($_REQUEST['month'],'tonum')*1;
if(!$_REQUEST['year'])
	$_REQUEST['year'] = date('Y');

$time = mktime(0,0,0,$_REQUEST['month'],1,$_REQUEST['year']);

DrawHeader(ProgramTitle());

if($_REQUEST['modfunc']=='create' && AllowEdit())
{
	$fy_RET = DBGet(DBQuery("SELECT START_DATE,END_DATE FROM SCHOOL_MARKING_PERIODS WHERE MP='FY' AND SCHOOL_ID='".UserSchool()."' AND SYEAR='".UserSyear()."'"));
	$fy_RET = $fy_RET[1];
    $title_RET = DBGet(DBQuery("SELECT ac.CALENDAR_ID,ac.TITLE,ac.DEFAULT_CALENDAR,ac.SCHOOL_ID,
	(SELECT coalesce(SHORT_NAME,TITLE) FROM SCHOOLS WHERE SYEAR=ac.SYEAR AND ID=ac.SCHOOL_ID) AS SCHOOL_TITLE,
	(SELECT min(SCHOOL_DATE) FROM ATTENDANCE_CALENDAR WHERE CALENDAR_ID=ac.CALENDAR_ID) AS START_DATE,
	(SELECT max(SCHOOL_DATE) FROM ATTENDANCE_CALENDAR WHERE CALENDAR_ID=ac.CALENDAR_ID) AS END_DATE 
	FROM ATTENDANCE_CALENDARS ac,STAFF s 
	WHERE ac.SYEAR='".UserSyear()."' 
	AND s.STAFF_ID='".User('STAFF_ID')."' 
	AND (s.SCHOOLS IS NULL OR position(','||ac.SCHOOL_ID||',' IN s.SCHOOLS)>0) 
	ORDER BY ".db_case(array('ac.SCHOOL_ID',"'".UserSchool()."'",0,'ac.SCHOOL_ID')).",ac.DEFAULT_CALENDAR ASC,ac.TITLE"));

	$message = '<SELECT name=copy_id><OPTION value="">'._('N/A');
	foreach($title_RET as $id=>$title)
	{
		if($_REQUEST['calendar_id'] && $title['CALENDAR_ID']==$_REQUEST['calendar_id'])
		{
			$message .=  '<OPTION value="'.$title['CALENDAR_ID'].'" selected>'.$title['TITLE'].(AllowEdit()&&$title['DEFAULT_CALENDAR']=='Y'?' ('._('Default').')':'');
			$default_id = $id;
			$prompt = $title['TITLE'];
		}
		else
            $message .= '<OPTION value="'.$title['CALENDAR_ID'].'">'.($title['SCHOOL_ID']!=UserSchool()?$title['SCHOOL_TITLE'].':':'').$title['TITLE'].(AllowEdit()&&$title['DEFAULT_CALENDAR']=='Y'?' ('._('Default').')':'');

	}
	$message .= '</SELECT>';
//FJ add <label> on checkbox
	$message = '<TABLE><TR><TD colspan="7"><table><tr class="st"><td>'.NoInput('<INPUT type="text" name="title"'.($_REQUEST['calendar_id']?' value="'.$title_RET[$default_id]['TITLE'].'"':'').'>',_('Title')).'</td><td><label>'.NoInput('<INPUT type="checkbox" name="default" value="Y"'.($_REQUEST['calendar_id']&&$title_RET[$default_id]['DEFAULT_CALENDAR']=='Y'?' checked':'').'>').' '._('Default Calendar for this School').'</label></td><td>'.NoInput($message,_('Copy Calendar')).'</td></tr></table></TD></TR>';
	$message .= '<TR><TD colspan="7" class="center"><table><tr class="st"><td>'._('From').' '.NoInput(PrepareDate($_REQUEST['calendar_id']&&$title_RET[$default_id]['START_DATE']?$title_RET[$default_id]['START_DATE']:$fy_RET['START_DATE'],'_min')).'</td><td>'._('To').' '.NoInput(PrepareDate($_REQUEST['calendar_id']&&$title_RET[$default_id]['END_DATE']?$title_RET[$default_id]['END_DATE']:$fy_RET['END_DATE'],'_max')).'</td></tr></table></TD></TR>';
	$message .= '<TR class="st"><TD><label>'.NoInput('<INPUT type="checkbox" value="Y" name="weekdays[0]"'.($_REQUEST['calendar_id']?' checked':'').'>').' '._('Sunday').'</label></TD><TD><label>'.NoInput('<INPUT type="checkbox" value="Y" name="weekdays[1]" checked />').' '._('Monday').'</label></TD><TD><label>'.NoInput('<INPUT type="checkbox" value="Y" name="weekdays[2]" checked />').' '._('Tuesday').'</label></TD><TD><label>'.NoInput('<INPUT type="checkbox" value="Y" name="weekdays[3]" checked />').' '._('Wednesday').'</label></TD><TD><label>'.NoInput('<INPUT type="checkbox" value="Y" name="weekdays[4]" checked />').' '._('Thursday').'</label></TD><TD><label>'.NoInput('<INPUT type="checkbox" value="Y" name="weekdays[5]" checked />').' '._('Friday').'<label></TD><TD><label>'.NoInput('<INPUT type="checkbox" value="Y" name="weekdays[6]"'.($_REQUEST['calendar_id']?' checked':'').'>').' '._('Saturday').'</label></TD></TR>';
	$message .= '<TR><TD colspan="7" class="center"><table><tr><td>'.NoInput('<INPUT type="text" name="minutes" size="3" maxlength="3">',_('Minutes')).'</td><td><span class="legend-gray">('.($_REQUEST['calendar_id']?_('Default is Full Day if Copy Calendar is N/A.').'<BR />'._('Otherwise Default is minutes from the Copy Calendar'):_('Default is Full Day')).')</span></td></tr></table></TD></TR>';
	$message .= '</TABLE>';
	if(Prompt($_REQUEST['calendar_id']?sprintf(_('Recreate %s calendar'),$prompt):_('Create new calendar'),'',$message))
	{
		if($_REQUEST['calendar_id'])
			$calendar_id = $_REQUEST['calendar_id'];
		else
		{
			$calendar_id = DBGet(DBQuery("SELECT ".db_seq_nextval('CALENDARS_SEQ')." AS CALENDAR_ID ".FROM_DUAL));
			$calendar_id = $calendar_id[1]['CALENDAR_ID'];
		}
		if($_REQUEST['default'])
			DBQuery("UPDATE ATTENDANCE_CALENDARS SET DEFAULT_CALENDAR=NULL WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."'");
		if($_REQUEST['calendar_id'])
			DBQuery("UPDATE ATTENDANCE_CALENDARS SET TITLE='".$_REQUEST['title']."',DEFAULT_CALENDAR='".$_REQUEST['default']."' WHERE CALENDAR_ID='".$calendar_id."'");
		else
			DBQuery("INSERT INTO ATTENDANCE_CALENDARS (CALENDAR_ID,SYEAR,SCHOOL_ID,TITLE,DEFAULT_CALENDAR) values('".$calendar_id."','".UserSyear()."','".UserSchool()."','".$_REQUEST['title']."','".$_REQUEST['default']."')");

		if($_REQUEST['copy_id'])
		{
			$weekdays_list = '\''.implode('\',\'',array_keys($_REQUEST['weekdays'])).'\'';
			if($_REQUEST['calendar_id'] && $_REQUEST['calendar_id']==$_REQUEST['copy_id'])
			{
				DBQuery("DELETE FROM ATTENDANCE_CALENDAR WHERE CALENDAR_ID='".$calendar_id."' AND (SCHOOL_DATE NOT BETWEEN '".$_REQUEST['day_min'].'-'.$_REQUEST['month_min'].'-'.$_REQUEST['year_min']."' AND '".$_REQUEST['day_max'].'-'.$_REQUEST['month_max'].'-'.$_REQUEST['year_max']."' OR extract(DOW FROM SCHOOL_DATE) NOT IN (".$weekdays_list."))");
//FJ fix bug MINUTES not numeric
				if($_REQUEST['minutes'] && intval($_REQUEST['minutes']) > 0)
					DBQuery("UPDATE ATTENDANCE_CALENDAR SET MINUTES='".$_REQUEST['minutes']."' WHERE CALENDAR_ID='".$calendar_id."'");
			}
			else
			{
				if($_REQUEST['calendar_id'])
					DBQuery("DELETE FROM ATTENDANCE_CALENDAR WHERE CALENDAR_ID='".$calendar_id."'");
//FJ fix bug MINUTES not numeric
				$create_calendar_sql = "INSERT INTO ATTENDANCE_CALENDAR (SYEAR,SCHOOL_ID,SCHOOL_DATE,MINUTES,CALENDAR_ID) (SELECT '".UserSyear()."','".UserSchool()."',SCHOOL_DATE,".($_REQUEST['minutes'] && intval($_REQUEST['minutes']) > 0?"'".$_REQUEST['minutes']."'":'MINUTES').",'".$calendar_id."' FROM ATTENDANCE_CALENDAR WHERE CALENDAR_ID='".$_REQUEST['copy_id']."' AND extract(DOW FROM SCHOOL_DATE) IN (".$weekdays_list.")";
				//FJ bugfix SQL bug empty school dates
				if($_REQUEST['month_min'] && $_REQUEST['month_max'])
				{
					$_REQUEST['date_min'] = $_REQUEST['day_min'].'-'.$_REQUEST['month_min'].'-'.$_REQUEST['year_min'];
					$_REQUEST['date_max'] = $_REQUEST['day_max'].'-'.$_REQUEST['month_max'].'-'.$_REQUEST['year_max'];
					
					if(mb_strlen($_REQUEST['date_min']) < 11 || mb_strlen($_REQUEST['date_max']) < 11)
						$_REQUEST['date_min'] = $_REQUEST['date_max'] = '';
					else
					{
						while(!VerifyDate($_REQUEST['date_min']))
						{
							$_REQUEST['day_min']--;
							$_REQUEST['date_min'] = $_REQUEST['day_min'].'-'.$_REQUEST['month_min'].'-'.$_REQUEST['year_min'];
						}
						while(!VerifyDate($_REQUEST['date_max']))
						{
							$_REQUEST['day_max']--;
							$_REQUEST['date_max'] = $_REQUEST['day_max'].'-'.$_REQUEST['month_max'].'-'.$_REQUEST['year_max'];
						}
					}
				}
				if($_REQUEST['date_min'] && $_REQUEST['date_max'])
					$create_calendar_sql .= " AND SCHOOL_DATE BETWEEN '".$_REQUEST['date_min']."' AND '".$_REQUEST['date_max']."'";
				$create_calendar_sql .= ")";
				DBQuery($create_calendar_sql);
			}
		}
		else
		{
			$begin = mktime(0,0,0,MonthNWSwitch($_REQUEST['month_min'],'to_num'),$_REQUEST['day_min']*1,$_REQUEST['year_min']) + 43200;
			$end = mktime(0,0,0,MonthNWSwitch($_REQUEST['month_max'],'to_num'),$_REQUEST['day_max']*1,$_REQUEST['year_max']) + 43200;

			$weekday = date('w',$begin);

			if($_REQUEST['calendar_id'])
				DBQuery("DELETE FROM ATTENDANCE_CALENDAR WHERE CALENDAR_ID='".$calendar_id."'");
			for($i=$begin;$i<=$end;$i+=86400)
			{
				if($_REQUEST['weekdays'][$weekday]=='Y')
//FJ fix bug MINUTES not numeric
					DBQuery("INSERT INTO ATTENDANCE_CALENDAR (SYEAR,SCHOOL_ID,SCHOOL_DATE,MINUTES,CALENDAR_ID) values('".UserSyear()."','".UserSchool()."','".date('d-M-y',$i)."',".($_REQUEST['minutes'] && intval($_REQUEST['minutes']) > 0?"'".$_REQUEST['minutes']."'":"'999'").",'".$calendar_id."')");
				$weekday++;
				if($weekday==7)
					$weekday = 0;
			}
		}

		$_REQUEST['calendar_id'] = $calendar_id;
		unset($_REQUEST['modfunc']);
		unset($_SESSION['_REQUEST_vars']['modfunc']);
		unset($_REQUEST['weekdays']);
		unset($_SESSION['_REQUEST_vars']['weekdays']);
		unset($_REQUEST['title']);
		unset($_SESSION['_REQUEST_vars']['title']);
		unset($_REQUEST['minutes']);
		unset($_SESSION['_REQUEST_vars']['minutes']);
		unset($_REQUEST['copy_id']);
		unset($_SESSION['_REQUEST_vars']['copy_id']);
	}
}

if($_REQUEST['modfunc']=='delete_calendar' && AllowEdit())
{
	if(DeletePrompt(_('Calendar')))
	{
		DBQuery("DELETE FROM ATTENDANCE_CALENDAR WHERE CALENDAR_ID='".$_REQUEST['calendar_id']."'");
		DBQuery("DELETE FROM ATTENDANCE_CALENDARS WHERE CALENDAR_ID='".$_REQUEST['calendar_id']."'");
		$default_RET = DBGet(DBQuery("SELECT CALENDAR_ID FROM ATTENDANCE_CALENDARS WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND DEFAULT_CALENDAR='Y'"));
		if(count($default_RET))
			$_REQUEST['calendar_id'] = $default_RET[1]['CALENDAR_ID'];
		else
		{
			$calendars_RET = DBGet(DBQuery("SELECT CALENDAR_ID FROM ATTENDANCE_CALENDARS WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."'"));

			if(count($calendars_RET))
				$_REQUEST['calendar_id'] = $calendars_RET[1]['CALENDAR_ID'];
			else
			{
				$error[] = _('There are no calendars setup yet.');
				unset($_REQUEST['calendar_id']);
			}
		}
		unset($_REQUEST['modfunc']);
		unset($_SESSION['_REQUEST_vars']['modfunc']);
	}
}

if(User('PROFILE')!='admin')
{
	$course_RET = DBGet(DBQuery("SELECT CALENDAR_ID FROM COURSE_PERIODS WHERE COURSE_PERIOD_ID='".UserCoursePeriod()."'"));
	if($course_RET[1]['CALENDAR_ID'])
		$_REQUEST['calendar_id'] = $course_RET[1]['CALENDAR_ID'];
	else
	{
		$default_RET = DBGet(DBQuery("SELECT CALENDAR_ID FROM ATTENDANCE_CALENDARS WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND DEFAULT_CALENDAR='Y'"));
		$_REQUEST['calendar_id'] = $default_RET[1]['CALENDAR_ID'];
	}
}
elseif(!$_REQUEST['calendar_id'])
{
	$default_RET = DBGet(DBQuery("SELECT CALENDAR_ID FROM ATTENDANCE_CALENDARS WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND DEFAULT_CALENDAR='Y'"));
	if(count($default_RET))
		$_REQUEST['calendar_id'] = $default_RET[1]['CALENDAR_ID'];
	else
	{
		$calendars_RET = DBGet(DBQuery("SELECT CALENDAR_ID FROM ATTENDANCE_CALENDARS WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."'"));

		if(count($calendars_RET))
			$_REQUEST['calendar_id'] = $calendars_RET[1]['CALENDAR_ID'];
		else
			$error[] = _('There are no calendars setup yet.');
	}
}
unset($_SESSION['_REQUEST_vars']['calendar_id']);

if($_REQUEST['modfunc']=='detail')
{
	if($_REQUEST['month_values'] && $_REQUEST['day_values'] && $_REQUEST['year_values'])
	{
		$_REQUEST['values']['SCHOOL_DATE'] = $_REQUEST['day_values']['SCHOOL_DATE'].'-'.$_REQUEST['month_values']['SCHOOL_DATE'].'-'.$_REQUEST['year_values']['SCHOOL_DATE'];
		if(!VerifyDate($_REQUEST['values']['SCHOOL_DATE']))
			unset($_REQUEST['values']['SCHOOL_DATE']);
	}

	if($_POST['button']==_('Save') && AllowEdit())
	{
		if($_REQUEST['values'])
		{			
			if($_REQUEST['event_id']!='new')
			{
				$sql = "UPDATE CALENDAR_EVENTS SET ";
				
				foreach($_REQUEST['values'] as $column=>$value)
					$sql .= $column."='".$value."',";

				$sql = mb_substr($sql,0,-1) . " WHERE ID='".$_REQUEST['event_id']."'";
				DBQuery($sql);

				//hook
				do_action('School_Setup/Calendar.php|update_calendar_event');
			}
			else
			{
//FJ add event repeat
				$i = 0;
				do {
					if ($i>0)//school date + 1 day
					{
						$_REQUEST['values']['SCHOOL_DATE'] = date('d-M-Y', mktime(0,0,0,MonthNWSwitch($_REQUEST['month_values']['SCHOOL_DATE'],'tonum'),$_REQUEST['day_values']['SCHOOL_DATE']+$i,$_REQUEST['year_values']['SCHOOL_DATE']));
					}
					$sql = "INSERT INTO CALENDAR_EVENTS ";

					$fields = 'ID,SYEAR,SCHOOL_ID,';
					$calendar_event_RET = DBGet(DBQuery("SELECT ".db_seq_nextval('CALENDAR_EVENTS_SEQ').' AS CALENDAR_EVENT_ID '.FROM_DUAL));
					$calendar_event_id = $calendar_event_RET[1]['CALENDAR_EVENT_ID'];
					$values = $calendar_event_id.",'".UserSyear()."','".UserSchool()."',";

					$go = 0;
					foreach($_REQUEST['values'] as $column=>$value)
					{
						if(!empty($value) || $value=='0')
						{
							$fields .= $column.',';
							$values .= "'".$value."',";
							$go = true;
						}
					}
					$sql .= '(' . mb_substr($fields,0,-1) . ') values(' . mb_substr($values,0,-1) . ')';

					if($go)
					{
						DBQuery($sql);

						//hook
						do_action('School_Setup/Calendar.php|create_calendar_event');
					}
					$i++;
				} while(is_numeric($_REQUEST['REPEAT']) && $i<=$_REQUEST['REPEAT']);
			}

			echo '<SCRIPT>var opener_reload = document.createElement("a"); opener_reload.href = "Modules.php?modname='.$_REQUEST['modname'].'&year='.$_REQUEST['year'].'&month='.MonthNWSwitch($_REQUEST['month'],'tochar').'"; opener_reload.target = "body"; window.opener.ajaxLink(opener_reload); window.close();</script>';

			unset($_REQUEST['values']);
			unset($_SESSION['_REQUEST_vars']['values']);
		}
	}
	elseif($_REQUEST['button']==_('Delete'))
	{
		if(DeletePrompt(_('Event')))
		{
			DBQuery("DELETE FROM CALENDAR_EVENTS WHERE ID='".$_REQUEST['event_id']."'");

			//hook
			do_action('School_Setup/Calendar.php|delete_calendar_event');

			echo '<SCRIPT>var opener_reload = document.createElement("a"); opener_reload.href = "Modules.php?modname='.$_REQUEST['modname'].'&year='.$_REQUEST['year'].'&month='.MonthNWSwitch($_REQUEST['month'],'tochar').'"; opener_reload.target = "body"; window.opener.ajaxLink(opener_reload); window.close();</script>';

			unset($_REQUEST['values']);
			unset($_SESSION['_REQUEST_vars']['values']);
			unset($_REQUEST['button']);
			unset($_SESSION['_REQUEST_vars']['button']);
		}
	}
	else
	{
		if($_REQUEST['event_id'])
		{
			if($_REQUEST['event_id']!='new')
			{
				$RET = DBGet(DBQuery("SELECT TITLE,DESCRIPTION,to_char(SCHOOL_DATE,'dd-MON-yy') AS SCHOOL_DATE FROM CALENDAR_EVENTS WHERE ID='".$_REQUEST['event_id']."'"), array('DESCRIPTION'=>'_formatContent'));
				$title = $RET[1]['TITLE'];
			}
			else
			{
				//FJ add translation
				$title = _('New Event');
				$RET[1]['SCHOOL_DATE'] = $_REQUEST['school_date'];
			}

			echo '<FORM action="Modules.php?modname='.$_REQUEST['modname'].'&modfunc=detail&event_id='.$_REQUEST['event_id'].'&month='.$_REQUEST['month'].'&year='.$_REQUEST['year'].'" METHOD="POST">';
		}
		else
		{
			//FJ add assigned date
			$RET = DBGet(DBQuery("SELECT a.TITLE,a.STAFF_ID,to_char(a.DUE_DATE,'dd-MON-yy') AS SCHOOL_DATE,a.DESCRIPTION,a.ASSIGNED_DATE,c.TITLE AS COURSE
			FROM GRADEBOOK_ASSIGNMENTS a,COURSES c
			WHERE (a.COURSE_ID=c.COURSE_ID
			OR c.COURSE_ID=(SELECT cp.COURSE_ID FROM COURSE_PERIODS cp WHERE cp.COURSE_PERIOD_ID=a.COURSE_PERIOD_ID))
			AND a.ASSIGNMENT_ID='".$_REQUEST['assignment_id']."'"), array('DESCRIPTION'=>'_formatContent'));
			$title = $RET[1]['TITLE'];
			$RET[1]['STAFF_ID'] = GetTeacher($RET[1]['STAFF_ID']);
		}

		echo '<BR />';

		PopTable('header',$title);

		echo '<TABLE><TR><TD>'._('Date').'</TD><TD>'.DateInput($RET[1]['SCHOOL_DATE'],'values[SCHOOL_DATE]','',false).'</TD></TR>';

		//FJ add assigned date
		if($RET[1]['ASSIGNED_DATE'])
			echo '<TR><TD>'._('Assigned Date').'</TD><TD>'.DateInput($RET[1]['ASSIGNED_DATE'],'values[ASSIGNED_DATE]','',false).'</TD></TR>';

		//FJ add event repeat
		if($_REQUEST['event_id']=='new')
		{
			echo '<TR><TD>'._('Event Repeat').'</TD><TD><input name="REPEAT" value="0" maxlength="3" size="1" type="number" min="0" />&nbsp;'._('Days').'</TD></TR>';
		}

		//hook
		do_action('School_Setup/Calendar.php|event_field');

		
		//FJ bugfix SQL bug value too long for type character varying(50)
		echo '<TR><TD>'._('Title').'</TD><TD>'.TextInput($RET[1]['TITLE'],'values[TITLE]', '', 'required maxlength="50"').'</TD></TR>';

		//FJ add course
		if($RET[1]['COURSE'])
			echo '<TR><TD>'._('Course').'</TD><TD>'.$RET[1]['COURSE'].'</TD></TR>';

		if($RET[1]['STAFF_ID'])
			echo '<TR><TD>'._('Teacher').'</TD><TD>'.TextInput($RET[1]['STAFF_ID'],'values[STAFF_ID]').'</TD></TR>';

		echo '<TR><TD>'._('Notes').'</TD><TD>'.TextAreaInput($RET[1]['DESCRIPTION'],'values[DESCRIPTION]').'</TD></TR>';

		if(AllowEdit())
		{
			echo '<TR><TD colspan="2" class="center">'.SubmitButton(_('Save'), 'button');
			if($_REQUEST['event_id']!='new')
				echo SubmitButton(_('Delete'), 'button');
			echo '</TD></TR>';
		}

		echo '</TABLE>';
		PopTable('footer');

		if($_REQUEST['event_id'])
			echo '</FORM>';

		unset($_REQUEST['values']);
		unset($_SESSION['_REQUEST_vars']['values']);
		unset($_REQUEST['button']);
		unset($_SESSION['_REQUEST_vars']['button']);
	}
}

if($_REQUEST['modfunc']=='list_events')
{
	if($_REQUEST['day_start'] && $_REQUEST['month_start'] && $_REQUEST['year_start'])
	{
		while(!VerifyDate($start_date = $_REQUEST['day_start'].'-'.$_REQUEST['month_start'].'-'.$_REQUEST['year_start']))
			$_REQUEST['day_start']--;
	}
	else
	{
		$min_date = DBGet(DBQuery("SELECT min(SCHOOL_DATE) AS MIN_DATE FROM ATTENDANCE_CALENDAR WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."'"));
		if($min_date[1]['MIN_DATE'])
			$start_date = $min_date[1]['MIN_DATE'];
		else
			$start_date = '01-'.mb_strtoupper(date('M-y'));
	}

	if($_REQUEST['day_end'] && $_REQUEST['month_end'] && $_REQUEST['year_end'])
	{
		while(!VerifyDate($end_date = $_REQUEST['day_end'].'-'.$_REQUEST['month_end'].'-'.$_REQUEST['year_end']))
			$_REQUEST['day_end']--;
	}
	else
	{
		$max_date = DBGet(DBQuery("SELECT max(SCHOOL_DATE) AS MAX_DATE FROM ATTENDANCE_CALENDAR WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."'"));
		if($max_date[1]['MAX_DATE'])
			$end_date = $max_date[1]['MAX_DATE'];
		else
			$end_date = mb_strtoupper(date('d-M-y'));
	}

	echo '<FORM action="Modules.php?modname='.$_REQUEST['modname'].'&modfunc='.$_REQUEST['modfunc'].'&month='.$_REQUEST['month'].'&year='.$_REQUEST['year'].'" METHOD="POST">';

	DrawHeader( '<A HREF="Modules.php?modname='.$_REQUEST['modname'].'&month='.$_REQUEST['month'].'&year='.$_REQUEST['year'].'">'._('Back to Calendar').'</A>' );

	DrawHeader(_('Timeframe').': '.PrepareDate($start_date,'_start').' '._('to').' '.PrepareDate($end_date,'_end') . ' ' . Buttons( _('Go') ) );

	$functions = array(
		'SCHOOL_DATE' => 'ProperDate',
		'DESCRIPTION' => '_formatDescription'
	);

	$events_RET = DBGet(DBQuery("SELECT ID,SCHOOL_DATE,TITLE,DESCRIPTION FROM CALENDAR_EVENTS WHERE SCHOOL_DATE BETWEEN '".$start_date."' AND '".$end_date."' AND SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."'"),$functions);

	ListOutput($events_RET,array('SCHOOL_DATE'=>'Date','TITLE'=>_('Event'),'DESCRIPTION'=>'Description'),'Event','Events');

	echo '</FORM>';
}

if(empty($_REQUEST['modfunc']))
{

	if(isset($error))
		echo ErrorMessage($error);

	$last = 31;
	while(!checkdate($_REQUEST['month'], $last, $_REQUEST['year']))
		$last--;

	$calendar_RET = DBGet(DBQuery("SELECT to_char(SCHOOL_DATE,'dd-MON-YY') AS SCHOOL_DATE,MINUTES,BLOCK FROM ATTENDANCE_CALENDAR WHERE SCHOOL_DATE BETWEEN '".date('d-M-y',$time)."' AND '".date('d-M-y',mktime(0,0,0,$_REQUEST['month'],$last,$_REQUEST['year']))."' AND SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND CALENDAR_ID='".$_REQUEST['calendar_id']."'"),array(),array('SCHOOL_DATE'));

	if($_REQUEST['minutes'])
	{
		foreach($_REQUEST['minutes'] as $date=>$minutes)
		{
			if($calendar_RET[$date])
			{
//				if($minutes!='0' && $minutes!='')
//FJ fix bug MINUTES not numeric
				if(intval($minutes) > 0)
					DBQuery("UPDATE ATTENDANCE_CALENDAR SET MINUTES='".$minutes."' WHERE SCHOOL_DATE='".$date."' AND SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND CALENDAR_ID='".$_REQUEST['calendar_id']."'");
				else
				{
					DBQuery("DELETE FROM ATTENDANCE_CALENDAR WHERE SCHOOL_DATE='".$date."' AND SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND CALENDAR_ID='".$_REQUEST['calendar_id']."'");
				}
			}
//			elseif($minutes!='0' && $minutes!='')
//FJ fix bug MINUTES not numeric
			elseif(intval($minutes) > 0)
				DBQuery("INSERT INTO ATTENDANCE_CALENDAR (SYEAR,SCHOOL_ID,SCHOOL_DATE,CALENDAR_ID,MINUTES) values('".UserSyear()."','".UserSchool()."','".$date."','".$_REQUEST['calendar_id']."','".$minutes."')");
		}
		$calendar_RET = DBGet(DBQuery("SELECT to_char(SCHOOL_DATE,'dd-MON-YY') AS SCHOOL_DATE,MINUTES,BLOCK FROM ATTENDANCE_CALENDAR WHERE SCHOOL_DATE BETWEEN '".date('d-M-y',$time)."' AND '".date('d-M-y',mktime(0,0,0,$_REQUEST['month'],$last,$_REQUEST['year']))."' AND SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND CALENDAR_ID='".$_REQUEST['calendar_id']."'"),array(),array('SCHOOL_DATE'));
		unset($_REQUEST['minutes']);
		unset($_SESSION['_REQUEST_vars']['minutes']);
	}
	if($_REQUEST['all_day'])
	{
		foreach($_REQUEST['all_day'] as $date=>$yes)
		{
			if($yes=='Y')
			{
				if($calendar_RET[$date])
					DBQuery("UPDATE ATTENDANCE_CALENDAR SET MINUTES='999' WHERE SCHOOL_DATE='".$date."' AND SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND CALENDAR_ID='".$_REQUEST['calendar_id']."' AND CALENDAR_ID='".$_REQUEST['calendar_id']."'");
				else
					DBQuery("INSERT INTO ATTENDANCE_CALENDAR (SYEAR,SCHOOL_ID,SCHOOL_DATE,CALENDAR_ID,MINUTES) values('".UserSyear()."','".UserSchool()."','".$date."','".$_REQUEST['calendar_id']."','999')");
			}
			else
				DBQuery("DELETE FROM ATTENDANCE_CALENDAR WHERE SCHOOL_DATE='".$date."' AND SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND CALENDAR_ID='".$_REQUEST['calendar_id']."'");
		}
		$calendar_RET = DBGet(DBQuery("SELECT to_char(SCHOOL_DATE,'dd-MON-YY') AS SCHOOL_DATE,MINUTES,BLOCK FROM ATTENDANCE_CALENDAR WHERE SCHOOL_DATE BETWEEN '".date('d-M-y',$time)."' AND '".date('d-M-y',mktime(0,0,0,$_REQUEST['month'],$last,$_REQUEST['year']))."' AND SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND CALENDAR_ID='".$_REQUEST['calendar_id']."'"),array(),array('SCHOOL_DATE'));
		unset($_REQUEST['all_day']);
		unset($_SESSION['_REQUEST_vars']['all_day']);
	}
	if($_REQUEST['blocks'])
	{
		foreach($_REQUEST['blocks'] as $date=>$block)
		{
			if($calendar_RET[$date])
			{
				DBQuery("UPDATE ATTENDANCE_CALENDAR SET BLOCK='".$block."' WHERE SCHOOL_DATE='".$date."' AND SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND CALENDAR_ID='".$_REQUEST['calendar_id']."'");
			}
		}
		$calendar_RET = DBGet(DBQuery("SELECT to_char(SCHOOL_DATE,'dd-MON-YY') AS SCHOOL_DATE,MINUTES,BLOCK FROM ATTENDANCE_CALENDAR WHERE SCHOOL_DATE BETWEEN '".date('d-M-y',$time)."' AND '".date('d-M-y',mktime(0,0,0,$_REQUEST['month'],$last,$_REQUEST['year']))."' AND SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND CALENDAR_ID='".$_REQUEST['calendar_id']."'"),array(),array('SCHOOL_DATE'));
		unset($_REQUEST['blocks']);
		unset($_SESSION['_REQUEST_vars']['blocks']);
	}

	echo '<FORM action="Modules.php?modname='.$_REQUEST['modname'].'" METHOD="POST">';
	if(AllowEdit())
	{
		$title_RET = DBGet(DBQuery("SELECT CALENDAR_ID,TITLE,DEFAULT_CALENDAR FROM ATTENDANCE_CALENDARS WHERE SCHOOL_ID='".UserSchool()."' AND SYEAR='".UserSyear()."' ORDER BY DEFAULT_CALENDAR ASC,TITLE"));
		foreach($title_RET as $title)
		{
			$options[$title['CALENDAR_ID']] = $title['TITLE'].($title['DEFAULT_CALENDAR']=='Y'?' ('._('Default').')':'');
			if($title['DEFAULT_CALENDAR']=='Y')
				$defaults++;
		}
		//FJ bugfix erase calendar onchange
		$calendar_onchange = '<script>var calendar_onchange = document.createElement("a"); calendar_onchange.href = "Modules.php?modname='.$_REQUEST['modname'].'&calendar_id="; calendar_onchange.target = "body";</script>';
		$link = $calendar_onchange.SelectInput($_REQUEST['calendar_id'],'calendar_id','',$options,false,' onchange="calendar_onchange.href += document.getElementById(\'calendar_id\').value; ajaxLink(calendar_onchange);" ',false).'<span class="nobr"><A HREF="Modules.php?modname='.$_REQUEST['modname'].'&modfunc=create">'.button('add')._('Create new calendar').'</A></span> | <span class="nobr"><A HREF="Modules.php?modname='.$_REQUEST['modname'].'&modfunc=create&calendar_id='.$_REQUEST['calendar_id'].'">'._('Recreate this calendar').'</A></span>&nbsp; <span class="nobr"><A HREF="Modules.php?modname='.$_REQUEST['modname'].'&modfunc=delete_calendar&calendar_id='.$_REQUEST['calendar_id'].'">'.button('remove')._('Delete this calendar').'</A></span>';
	}

	DrawHeader(PrepareDate(mb_strtoupper(date("d-M-y",$time)),'',false,array('M'=>1,'Y'=>1,'submit'=>true)).' <A HREF="Modules.php?modname='.$_REQUEST['modname'].'&modfunc=list_events&month='.$_REQUEST['month'].'&year='.$_REQUEST['year'].'">'._('List Events').'</A>',SubmitButton(_('Save')));

	DrawHeader($link);

	if(AllowEdit() && $defaults!=1)
//FJ css WPadmin
		echo ErrorMessage(array($defaults?_('This school has more than one default calendar!'):_('This school does not have a default calendar!')));
	echo '<BR />';

	$events_RET = DBGet(DBQuery("SELECT ID,to_char(SCHOOL_DATE,'dd-MON-yy') AS SCHOOL_DATE,TITLE,DESCRIPTION FROM CALENDAR_EVENTS WHERE SCHOOL_DATE BETWEEN '".date('d-M-y',$time)."' AND '".date('d-M-y',mktime(0,0,0,$_REQUEST['month'],$last,$_REQUEST['year']))."' AND SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."'"),array(),array('SCHOOL_DATE'));
	
	if(User('PROFILE')=='parent' || User('PROFILE')=='student')
		$assignments_RET = DBGet(DBQuery("SELECT ASSIGNMENT_ID AS ID,to_char(a.DUE_DATE,'dd-MON-yy') AS SCHOOL_DATE,a.TITLE,'Y' AS ASSIGNED 
		FROM GRADEBOOK_ASSIGNMENTS a,SCHEDULE s 
		WHERE (a.COURSE_PERIOD_ID=s.COURSE_PERIOD_ID OR a.COURSE_ID=s.COURSE_ID) 
		AND s.STUDENT_ID='".UserStudentID()."' 
		AND (a.DUE_DATE BETWEEN s.START_DATE AND s.END_DATE OR s.END_DATE IS NULL) 
		AND (a.ASSIGNED_DATE<=CURRENT_DATE OR a.ASSIGNED_DATE IS NULL) 
		AND a.DUE_DATE BETWEEN '".date('d-M-y',$time)."' AND '".date('d-M-y',mktime(0,0,0,$_REQUEST['month'],$last,$_REQUEST['year']))."'"),array(),array('SCHOOL_DATE'));
		
	elseif(User('PROFILE')=='teacher')
		$assignments_RET = DBGet(DBQuery("SELECT ASSIGNMENT_ID AS ID,to_char(a.DUE_DATE,'dd-MON-yy') AS SCHOOL_DATE,a.TITLE,CASE WHEN a.ASSIGNED_DATE<=CURRENT_DATE OR a.ASSIGNED_DATE IS NULL THEN 'Y' ELSE NULL END AS ASSIGNED 
		FROM GRADEBOOK_ASSIGNMENTS a 
		WHERE a.STAFF_ID='".User('STAFF_ID')."' 
		AND a.DUE_DATE BETWEEN '".date('d-M-y',$time)."' AND '".date('d-M-y',mktime(0,0,0,$_REQUEST['month'],$last,$_REQUEST['year']))."'"),array(),array('SCHOOL_DATE'));

	$skip = date("w",$time);

//FJ css WPadmin
	echo '<TABLE style="background-color:#EEEEEE;" id="calendar"><THEAD><TR style="text-align:center; background-color:black; color:white;">';
	echo '<TH>'.mb_substr(_('Sunday'),0,3).'<span>'.mb_substr(_('Sunday'),3).'</span>'.'</TH><TH>'.mb_substr(_('Monday'),0,3).'<span>'.mb_substr(_('Monday'),3).'</span>'.'</TH><TH>'.mb_substr(_('Tuesday'),0,3).'<span>'.mb_substr(_('Tuesday'),3).'</span>'.'</TH><TH>'.mb_substr(_('Wednesday'),0,3).'<span>'.mb_substr(_('Wednesday'),3).'</span>'.'</TH><TH>'.mb_substr(_('Thursday'),0,3).'<span>'.mb_substr(_('Thursday'),3).'</span>'.'</TH><TH>'.mb_substr(_('Friday'),0,3).'<span>'.mb_substr(_('Friday'),3).'</span>'.'</TH><TH>'.mb_substr(_('Saturday'),0,3).'<span>'.mb_substr(_('Saturday'),3).'</span>'.'</TH>';
	echo '</TR></THEAD><TBODY><TR>';

	if($skip)
	{
		echo '<td colspan="' . $skip . '" class="calendar-skip">&nbsp;</td>';
		$return_counter = $skip;
	}
	for($i=1;$i<=$last;$i++)
	{
		$day_time = mktime(0,0,0,$_REQUEST['month'],$i,$_REQUEST['year']);
		$date = mb_strtoupper(date('d-M-y',$day_time));

		echo '<TD class="valign-top" style="height:100%; background-color:'.($calendar_RET[$date][1]['MINUTES']?$calendar_RET[$date][1]['MINUTES']=='999'?'#EEFFEE':'#EEEEFF':'#FFEEEE').';">
		<table class="calendar-day'.((AllowEdit() || $calendar_RET[$date][1]['MINUTES'] || count($events_RET[$date]) || count($assignments_RET[$date])) ? ' hover' : '').'"><tr>
		<td style="width:5px;" class="valign-top">'.((count($events_RET[$date]) || count($assignments_RET[$date])) ? '<span class="calendar-day-bold">'.$i.'</span>' : $i).'</td>
		<td>';

		if(AllowEdit())
		{
			echo '<TABLE style="width:95px;"><TR><TD style="text-align:right;">';
			if($calendar_RET[$date][1]['MINUTES']=='999')
//FJ icones
				echo CheckboxInput($calendar_RET[$date],"all_day[$date]", '', '', false, button('check'), '', true, 'title="'._('All Day').'"');
			elseif($calendar_RET[$date][1]['MINUTES'])
				echo TextInput($calendar_RET[$date][1]['MINUTES'],"minutes[$date]",'','size=3');
			else
			{
				echo '<INPUT type="checkbox" name="all_day['.$date.']" value="Y" title="'._('All Day').'" />&nbsp;';
//FJ fix bug MINUTES not numeric
				echo '<INPUT type="number" min="1" max="998" name="minutes['.$date.']" size="3" title="'._('Minutes').'" />';
			}
			echo '</TD></TR></TABLE>';
		}
		$blocks_RET = DBGet(DBQuery("SELECT DISTINCT BLOCK FROM SCHOOL_PERIODS WHERE SYEAR='".UserSyear()."' AND SCHOOL_ID='".UserSchool()."' AND BLOCK IS NOT NULL ORDER BY BLOCK"));
		if(count($blocks_RET)>0)
		{
			unset($options);
			foreach($blocks_RET as $block)
				$options[$block['BLOCK']] = $block['BLOCK'];

			if ( $calendar_RET[$date][1]['BLOCK']
				|| User( 'PROFILE' ) === 'admin' )
			{
				echo SelectInput(
					$calendar_RET[$date][1]['BLOCK'],
					"blocks[" . $date . "]",
					'',
					$options
				);
			}
		}
		echo '</td></tr><tr><TD colspan="2" style="height:50px;" class="valign-top">';

		if(count($events_RET[$date]))
		{
			echo '<TABLE style="border-collapse:separate; border-spacing:2px;">';

			//FJ display event link only if description or if admin
			foreach($events_RET[$date] as $event)
				echo '<TR class="center">
				<TD style="width:1px; background-color:#000;"></TD>
				<TD>'.(AllowEdit() || $event['DESCRIPTION'] ? '<A HREF="#" onclick=\'javascript:window.open("Modules.php?modname='.$_REQUEST['modname'].'&modfunc=detail&event_id='.$event['ID'].'&year='.$_REQUEST['year'].'&month='.MonthNWSwitch($_REQUEST['month'],'tochar').'","blank","width=500,height=400"); return false;\'>'.($event['TITLE']?$event['TITLE']:'***').'</A>' : ($event['TITLE']?$event['TITLE']:'***')).'</TD>
				</TR>';

			if(count($assignments_RET[$date]))
			{
				foreach($assignments_RET[$date] as $event)
					echo '<TR class="center">
					<TD style="width:1px; background-color:'.($event['ASSIGNED']=='Y'?'#00FF00':'#FF0000').'"></TD>
					<TD>'.'<A HREF="#" onclick=\'javascript:window.open("Modules.php?modname='.$_REQUEST['modname'].'&modfunc=detail&assignment_id='.$event['ID'].'&year='.$_REQUEST['year'].'&month='.MonthNWSwitch($_REQUEST['month'],'tochar').'","blank","width=500,height=400"); return false;\'>'.$event['TITLE'].'</A></TD>
					</TR>';
			}
			echo '</TABLE>';
		}
		elseif(count($assignments_RET[$date]))
		{
			echo '<TABLE style="border-collapse:separate; border-spacing:2px;">';
			foreach($assignments_RET[$date] as $event)
				echo '<TR class="center">
				<TD style="width:1px; background-color:'.($event['ASSIGNED']=='Y'?'#00FF00':'#FF0000').'"></TD>
				<TD>'.'<A HREF="#" onclick=\'javascript:window.open("Modules.php?modname='.$_REQUEST['modname'].'&modfunc=detail&assignment_id='.$event['ID'].'&year='.$_REQUEST['year'].'&month='.MonthNWSwitch($_REQUEST['month'],'tochar').'","blank","width=500,height=400"); return false;\'>'.$event['TITLE'].'</A></TD>
				</TR>';
			echo '</TABLE>';
		}

		echo '</TD></TR>';
		if(AllowEdit())
		{
		//FJ days numbered
			echo '<tr style="height:100%; vertical-align:bottom;"><td>'.button('add','','"#" onclick=\'javascript:window.open("Modules.php?modname='.$_REQUEST['modname'].'&modfunc=detail&event_id=new&school_date='.$date.'&year='.$_REQUEST['year'].'&month='.MonthNWSwitch($_REQUEST['month'],'tochar').'","blank","width=500,height=400"); return false;\' title="'._('New Event').'"').'</td>';
				
			if (SchoolInfo('NUMBER_DAYS_ROTATION') !== null)
			{
				echo '<td style="text-align:right;">'.(($dayNumber = dayToNumber($day_time))?_('Day').'&nbsp;'.$dayNumber:'&nbsp;').'</td>';
			}
			echo '</tr>';
		}
		elseif (SchoolInfo('NUMBER_DAYS_ROTATION') !== null)
		{
			echo '<tr><td style="text-align:right;">'.(($dayNumber = dayToNumber($day_time))?_('Day').'&nbsp;'.$dayNumber:'&nbsp;').'</td></tr>';
		}
		echo '</table></TD>';
		$return_counter++;

		if($return_counter%7==0)
			echo '</TR><TR>';
	}

	if($return_counter%7!=0)
	{
		$skip = 7 - $return_counter%7;
		echo '<td colspan="' . $skip . '" class="calendar-skip">&nbsp;</td>';
	}

	echo '</TR></TBODY></TABLE>';

	echo '<BR /><span class="center">'.SubmitButton(_('Save')).'</span>';
	echo '<BR /><BR /></FORM>';
}


function _formatContent($value,$column)
{	global $THIS_RET;

	if (AllowEdit())
		return $value;

	$id = $THIS_RET['ID'];

	//Linkify
	include_once('ProgramFunctions/Linkify.fnc.php');

	return Linkify($value);
}

function _formatDescription( $value, $column )
{
	global $THIS_RET;

	$id = $THIS_RET['ID'];

	//Linkify
	include_once( 'ProgramFunctions/Linkify.fnc.php' );

	$value_br_url = Linkify( nl2br( $value ) );

	if ( isset( $_REQUEST['_ROSARIO_PDF'] )
		|| ( $value_br_url == $value
			&& mb_strlen( $value ) < 50 ) )
	{
		$return = $value_br_url;
	}
	//FJ responsive rt td too large
	else
	{
		$return = includeOnceColorBox( 'divEventDescription' . $id ) .
			'<div id="divEventDescription' . $id . '" class="rt2colorBox">' . $value_br_url . '</div>';
	}

	return $return;
}
