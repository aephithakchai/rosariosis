<?php
DrawHeader(ProgramTitle());

if($_REQUEST['day_start'] && $_REQUEST['month_start'] && $_REQUEST['year_start'])
{
	while(!VerifyDate($start_date = $_REQUEST['day_start'].'-'.$_REQUEST['month_start'].'-'.$_REQUEST['year_start']))
		$_REQUEST['day_start']--;
}
else
	$start_date = '01-'.mb_strtoupper(date('M-y'));

if($_REQUEST['day_end'] && $_REQUEST['month_end'] && $_REQUEST['year_end'])
{
	while(!VerifyDate($end_date = $_REQUEST['day_end'].'-'.$_REQUEST['month_end'].'-'.$_REQUEST['year_end']))
		$_REQUEST['day_end']--;
}
else
	$end_date = DBDate();

echo '<FORM action="'.PreparePHP_SELF().'" method="POST">';
DrawHeader(_('Timeframe').':'.PrepareDate($start_date,'_start').' '._('to').' '.PrepareDate($end_date,'_end').' : <INPUT type="submit" value="'._('Go').'">');
echo '</FORM>';

$enrollment_RET = DBGet(DBQuery("SELECT se.START_DATE AS START_DATE,NULL AS END_DATE,se.START_DATE AS DATE,se.STUDENT_ID,s.LAST_NAME||', '||s.FIRST_NAME AS FULL_NAME,sch.TITLE 
FROM STUDENT_ENROLLMENT se,STUDENTS s,SCHOOLS sch 
WHERE s.STUDENT_ID=se.STUDENT_ID 
AND se.START_DATE BETWEEN '".$start_date."' AND '".$end_date."'
AND sch.ID=se.SCHOOL_ID 
UNION 
SELECT NULL AS START_DATE,se.END_DATE AS END_DATE,se.END_DATE AS DATE,se.STUDENT_ID,s.LAST_NAME||', '||s.FIRST_NAME AS FULL_NAME,sch.TITLE 
FROM STUDENT_ENROLLMENT se,STUDENTS s,SCHOOLS sch 
WHERE s.STUDENT_ID=se.STUDENT_ID 
AND se.END_DATE BETWEEN '".$start_date."' AND '".$end_date."' 
AND sch.ID=se.SCHOOL_ID 
ORDER BY DATE DESC"),array('START_DATE'=>'ProperDate','END_DATE'=>'ProperDate'));

$columns = array('FULL_NAME'=>_('Student'),'STUDENT_ID'=>sprintf(_('%s ID'),Config('NAME')),'TITLE'=>_('School'),'START_DATE'=>_('Enrolled'),'END_DATE'=>_('Dropped'));
ListOutput($enrollment_RET,$columns,'Enrollment Record','Enrollment Records');
?>
