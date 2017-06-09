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

	$dque="SELECT b.comp_id, b.parid FROM mpHR_Batches b LEFT JOIN capp_info a ON a.comp_id=b.comp_id LEFT JOIN company_info ON a.sno=company_info.sno LEFT JOIN options o ON o.sno=company_info.sno WHERE company_info.status='ER' AND o.akkupay='Y' AND b.process='N' ".$version_clause." ORDER BY b.sno";
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);
		$batch_sno=$drow[1];

		$dbObj = new dbConnection;
		$dbObj->getActiveDatabase($companyuser,'AppDB');
		$db = $dbObj->con_db;

		$mhque="SELECT l.serial_no,l.mphr_username,l.mphr_password,CONCAT(l.loccode,'_',SUBSTRING(MD5(UNIX_TIMESTAMP()),1,8)) as bcode FROM contact_manage l, mpHR_payUnitBatch b, mpHR_payGroups g WHERE l.mphr_username!='' AND l.mphr_password!='' AND b.sno=g.parid AND b.process='N' AND b.paydate>CURRENT_DATE() AND l.serial_no=g.locid AND b.sno='$batch_sno'";
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

					$payBatches=$mphr->getACPayBatches($mhrow['serial_no'],$batch_sno);

					// START PUSHING ONLY PROJECT AND WORKSITE CODES OF THE ASSIGNMENTS INCLUDED IN PAYROLL BATCHES 
					if(count($payBatches)>0)
					{
						for($i=0;$i<count($payBatches);$i++)
						{
							$mphr->syncProjectCodes($payBatches[$i]['sno']);
						}
					}

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

					// START PUSHING REGULAR / QUICK PAYROLL BATCHES AND PAY GROUPS AND PAY DATA / DEDUCTIONS / GARNISHMENTS / DIRECT DEPOSITS
					if(count($payBatches)>0)
					{
						for($i=0;$i<count($payBatches);$i++)
						{
							if($payBatches[$i]['paybtype']=="Regular")
							{
								if($payBatch=$mphr->addBatch($payBatches[$i]))
								{
									$payBatches[$i]=$payBatch;
									$batchid=$payBatches[$i]['sno'];

									$payData=$mphr->getACPayData($payBatches[$i]['sno'],$payBatches[$i]['mpbnum'],$payBatches[$i]['paybcode']);
									if(count($payData)>0)
									{
										for($j=0;$j<count($payData);$j++)
										{
											$mphr->addPayData($payData[$j]);
										}
									}

									// We should include this file after we push the pay data. If any of the new employee data push failed then for those employees the DD/DEDUCT/GARNISHMENTS are not flagged as pushed and included for next push.
									require('include/ded_garn_dep_queries.inc');

									$payDeductions=$mphr->getACPayDeductions($payBatches[$i]['sno'],$payBatches[$i]['mpbnum'],$payBatches[$i]['paybcode']);
									if(count($payDeductions)>0)
									{
										for($j=0;$j<count($payDeductions);$j++)
										{
											$mphr->addEmpDeduction($payDeductions[$j]);
										}
									}
			
									$payGarnishments=$mphr->getACPayGarnishments($payBatches[$i]['sno'],$payBatches[$i]['mpbnum'],$payBatches[$i]['paybcode']);
									if(count($payGarnishments)>0)
									{
										for($j=0;$j<count($payGarnishments);$j++)
										{
											$mphr->addEmpGarnishment($payGarnishments[$j]);
										}
									}
			
									$payDeposits=$mphr->getACPayDeposits($payBatches[$i]['sno'],$payBatches[$i]['mpbnum'],$payBatches[$i]['paybcode']);
									if(count($payDeposits)>0)
									{
										for($j=0;$j<count($payDeposits);$j++)
										{
											$mphr->addEmpDeposit($payDeposits[$j]);
										}
									}

									$mphr->res_error="";

									$sque="SELECT COUNT(1) FROM mpHR_payData p LEFT JOIN mpHR_payGroups g ON p.parid=g.sno WHERE g.parid='".$payBatches[$i]['sno']."' AND p.process!='Y'";
									$sres=mysql_query($sque,$db);
									$srow=mysql_fetch_row($sres);
									if($srow[0]>0)
									{
										$mphr->updatePayBatchGrossAmount($payBatches[$i]);
										$mphr->updatePayBatchStatus($payBatches[$i],"P");
									}
									else
									{
										$sdedque="SELECT COUNT(1) FROM mpHR_deductions d WHERE d.type='D' AND d.parid='".$payBatches[$i]['sno']."' AND (d.process!='Y' OR d.PushStatus='Conflict' OR d.PushStatus='Deleted')";
										$sdedres=mysql_query($sdedque,$db);
										$sdedrow=mysql_fetch_row($sdedres);
	
										$sgarque="SELECT COUNT(1) FROM mpHR_deductions g WHERE g.type='G' AND g.parid='".$payBatches[$i]['sno']."' AND (g.process!='Y' OR g.PushStatus='Conflict' OR g.PushStatus='Deleted')";
										$sgarres=mysql_query($sgarque,$db);
										$sgarrow=mysql_fetch_row($sgarres);
	
										$sddque="SELECT COUNT(1) FROM mpHR_deposits d WHERE d.parid='".$payBatches[$i]['sno']."' AND (process!='Y' OR PushStatus='Conflict')";
										$sddres=mysql_query($sddque,$db);
										$sddrow=mysql_fetch_row($sddres);
	
										if($sdedrow[0]>0 || $sgarrow[0]>0 || $sddrow[0]>0)
										{
											$mphr->updatePayBatchStatus($payBatches[$i],"C");
										}
										else
										{
											$mphr->updatePayBatchStatus($payBatches[$i],"Y");
										}
									}
								}
							}
							else
							{
								$payBatches[$i]['paybcode']=$mphr->bcode;
								$payData=$mphr->getACPayData($payBatches[$i]['sno'],'',$payBatches[$i]['paybcode']);
								if(count($payData)>0)
								{	
									$rdata = $mphr->addQuickPayData($payData);
									$mphr->addQuickBatch($payBatches[$i],$rdata);
								}
							}

							// UPDATE PROJECT AND WORKSITE COES FOR EMPLOYEES WITH SINGLE ASSIGNMENT ONLY
							$mphr->updateEmpWorkSiteCodes($payBatches[$i]['sno']);
						}
					}
				}
			}
		}
	}
?>
