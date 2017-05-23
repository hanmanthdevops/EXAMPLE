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

		$mhque="SELECT l.serial_no,l.mphr_username,l.mphr_password,CONCAT(l.loccode,'_',SUBSTRING(MD5(UNIX_TIMESTAMP()),1,8)) as bcode FROM contact_manage l, mpHR_personData p WHERE l.mphr_username!='' AND l.mphr_password!='' AND l.serial_no=p.locid AND p.process='N' GROUP BY l.serial_no";
		$mhres=mysql_query($mhque,$db);
		while($mhrow=mysql_fetch_assoc($mhres))
		{
			if($mhrow['mphr_username']!="" && $mhrow['mphr_password']!="")
			{
				$authData = array('username' => $mhrow['mphr_username'], 'password' => $mhrow['mphr_password']);
				if($mphr->doAuth($authData))
				{
					$mphr->bcode=$mhrow['bcode'];

					$mphr->clear_debug();

					// START PUSHING ANY NEW EMPLOYEES / UPDATES NOT PUSHED YET
					$personsData=$mphr->getPersonsData($mhrow['serial_no']);
					if(count($personsData)>0)
					{
						for($i=0;$i<count($personsData);$i++)
						{
							if($personsData[$i]['employee_id']>0)
							{
								if($mphrPersonData = $mphr->lookupPerson($personsData[$i],"employee_id"))
								{
									$mphr->updatePerson($personsData[$i]);
								}
							}
							else if($personsData[$i]['employee_id']==0)
							{
								if($mphrPersonData = $mphr->lookupPerson($personsData[$i],"soc_sec_num"))
								{
									$personsData[$i]['employee_id']=$mphrPersonData['employee_id'];
									$mphr->updateMPHRCheck($personsData[$i]['locid'],$personsData[$i]['empsno'],$personsData[$i]['employee_id']);
									$mphr->updatePerson($personsData[$i]);
								}
								else
								{
									$mphr->insertPerson($personsData[$i]);
								}
							}
							else
							{
								$mphr->insertPerson($personsData[$i]);
							}
						}
					}
				}
			}
		}
	}
?>