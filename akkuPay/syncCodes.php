<?php
	ini_set("display_errors","1");

	require_once("include/class.dbconnection.inc");
	require_once('include/nusoap.php');
	require_once('include/json_functions.inc');
	require_once('include/ap_functions.inc');
	require_once('include/sysdb.inc');

	$dbObj = new dbConnection;
	$dbObj->getActiveDatabase('','MainDB');
	$maindb = $dbObj->con_maindb;

	require("class.mpHR.php");
	$mphr=new mpHR();
	$mphr->spAllowances = $mphr->setStatePrimaryAllowances();

	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno LEFT JOIN options ON options.sno=company_info.sno where company_info.status='ER' AND options.akkupay='Y' ".$version_clause." ORDER BY company_info.sno";
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);

		$dbObj = new dbConnection;
		$dbObj->getActiveDatabase($companyuser,'AppDB');
		$db = $dbObj->con_db;

		$mhque="SELECT serial_no,mphr_username,mphr_password,CONCAT(loccode,'_',SUBSTRING(MD5(UNIX_TIMESTAMP()),1,8)) as bcode FROM contact_manage WHERE mphr_username!='' AND mphr_password!=''";
		$mhres=mysql_query($mhque,$db);
		while($mhrow=mysql_fetch_assoc($mhres))
		{
			if($mhrow['mphr_username']!="" && $mhrow['mphr_password']!="")
			{
				$authData = array('username' => $mhrow['mphr_username'], 'password' => $mhrow['mphr_password']);
				if($mphr->doAuth($authData))
				{
					$mphr->bcode=$mhrow['bcode'];

					/***************************************************
					Syncing CODES from MPHR to Akken
					***************************************************/
					$mphr->syncDeductionCodes();
					$mphr->syncPayCodes();
					$mphr->syncPayGroupCodes();

					// Client Work Site are specific to Locations, so we track them by location even though they are syncing from MPHR to Akken
					$mphr->syncClientWorkSites($mhrow['serial_no']);

					/***************************************************
					Syncing CODES from Akken to MPHR
					***************************************************/
					$mphr->syncDepartmentCodes($mhrow['serial_no']);
					$mphr->syncShiftCodes($mhrow['serial_no']);
					$mphr->syncLocationCodes($mhrow['serial_no']);

					$mphr->clear_debug();
				}
			}
		}
	}
?>