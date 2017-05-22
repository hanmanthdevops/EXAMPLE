<?php
	$dedCodes=array();
	$garCodes=array();

	$bded_que="SELECT c.location as locid,e.sno as empsno,mphr.employee_id as employee_id,'D' as type,d.title,d.deduction_code as deduct_code,d.frequency as calc_freq,d.calc_method as garn_method,d.amount,d.start_date,d.stop_date,'' as ins_class,'New' as PushStatus,'Included in Push -- New DD Info, 1st time pushing' as PushDescription
		FROM hrcon_deduct d 
		LEFT JOIN emp_list e ON e.username=d.username AND d.ustatus='active' 
		LEFT JOIN mpHR_payData mp ON mp.AC_EmpID = e.sno 
		LEFT JOIN mpHR_payGroups mg ON mp.parid=mg.sno 
		LEFT JOIN hrcon_compen c ON e.username=c.username AND c.ustatus='active' 
		LEFT JOIN mpHR_locEmpInfo mphr ON e.sno=mphr.empsno AND mphr.locid=c.location 
		WHERE mg.parid='$batchid' 
		AND mp.employee_id!=0
		AND d.deduction_code!=''  
		AND c.paygroupcode=mg.payunit 
		AND ((d.start_date='' OR (str_to_date(d.start_date,'%m/%d/%Y')>=mg.paysdate && str_to_date(d.start_date,'%m/%d/%Y')<=mg.payedate)) AND (d.stop_date='' OR (str_to_date(d.stop_date,'%m/%d/%Y')>=mg.payedate)))
		GROUP BY d.sno
	";
	$bdedres=mysql_query($bded_que,$db);
	while($bdedrow=mysql_fetch_assoc($bdedres))
	{
		$bdedrow=checkDeductions($bdedrow);
		$ibdedque="INSERT INTO mpHR_deductions (parid,locid,empsno,employee_id,type,title,deduct_code,calc_freq,garn_method,amount,start_date,stop_date,ins_class,PushStatus,PushDescription,process,mpError) VALUES ('".$batchid."','".$bdedrow['locid']."','".$bdedrow['empsno']."','".$bdedrow['employee_id']."','".$bdedrow['type']."','".$bdedrow['title']."','".$bdedrow['deduct_code']."','".$bdedrow['calc_freq']."','".$bdedrow['garn_method']."','".$bdedrow['amount']."','".$bdedrow['start_date']."','".$bdedrow['stop_date']."','".$bdedrow['ins_class']."','".$bdedrow['PushStatus']."','".$bdedrow['PushDescription']."','N','')";
		mysql_query($ibdedque,$db);
	}

	$bgar_que="SELECT c.location as locid,e.sno as empsno,mphr.employee_id as employee_id,'G' as type,g.title,g.deduction_code as deduct_code,g.frequency as calc_freq,g.calc_method as garn_method,g.amount,g.start_date,g.stop_date,'' as ins_class,g.garnishment_type as garn_type,g.docket_no,g.date_issued,g.sdu_statecode as sdu_state,g.sdu_caseid as sdu_case_id,'New' as PushStatus,'Included in Push -- New DD Info, 1st time pushing' as PushDescription
		FROM hrcon_garnishments g 
		LEFT JOIN emp_list e ON e.username=g.username AND g.ustatus='active' 
		LEFT JOIN mpHR_payData mp ON mp.AC_EmpID = e.sno 
		LEFT JOIN mpHR_payGroups mg ON mp.parid=mg.sno 
		LEFT JOIN hrcon_compen c ON e.username=c.username AND c.ustatus='active' 
		LEFT JOIN mpHR_locEmpInfo mphr ON e.sno=mphr.empsno AND mphr.locid=c.location 
		WHERE mg.parid='$batchid' 
		AND mp.employee_id!=0
		AND g.deduction_code!=''  
		AND c.paygroupcode=mg.payunit 
		AND ((g.start_date='' OR (str_to_date(g.start_date,'%m/%d/%Y')>=mg.paysdate && str_to_date(g.start_date,'%m/%d/%Y')<=mg.payedate)) AND (g.stop_date='' OR (str_to_date(g.stop_date,'%m/%d/%Y')>=mg.payedate)))
		GROUP BY g.sno
	";
	$bgarres=mysql_query($bgar_que,$db);
	while($bgarrow=mysql_fetch_assoc($bgarres))
	{
		$bgarrow=checkGarnishments($bgarrow);
		$ibgarque="INSERT INTO mpHR_deductions (parid,locid,empsno,employee_id,type,title,deduct_code,calc_freq,garn_method,amount,start_date,stop_date,ins_class,garn_type,docket_no,date_issued,sdu_state,sdu_case_id,PushStatus,PushDescription,process,mpError) VALUES ('".$batchid."','".$bgarrow['locid']."','".$bgarrow['empsno']."','".$bgarrow['employee_id']."','".$bgarrow['type']."','".$bgarrow['title']."','".$bgarrow['deduct_code']."','".$bgarrow['calc_freq']."','".$bgarrow['garn_method']."','".$bgarrow['amount']."','".$bgarrow['start_date']."','".$bgarrow['stop_date']."','".$bgarrow['ins_class']."','".$bgarrow['garn_type']."','".$bgarrow['docket_no']."','".$bgarrow['date_issued']."','".$bgarrow['sdu_state']."','".$bgarrow['sdu_case_id']."','".$bgarrow['PushStatus']."','".$bgarrow['PushDescription']."','N','')";
		mysql_query($ibgarque,$db);
	}

	$bdd_que="SELECT c.location as locid,e.sno as empsno,mphr.employee_id as employee_id,
		IF((TRIM(d.bankrtno)!='' AND TRIM(d.bankacno)!=''),SUBSTR(d.acc1_type,1,1),'') as acct_type1,
		IF((TRIM(d.bankrtno)!='' AND TRIM(d.bankacno)!=''),TRIM(d.bankrtno),'') as transit_no1,
		IF((TRIM(d.bankrtno)!='' AND TRIM(d.bankacno)!=''),TRIM(d.bankacno),'') as account_no1,
		IF((TRIM(d.bankrtno)!='' AND TRIM(d.bankacno)!=''),IF(SUBSTR(d.acc1_payperiod,1,1)='A','F','P'),'') as dep_method1,
		IF((TRIM(d.bankrtno)!='' AND TRIM(d.bankacno)!=''),d.acc1_amt,'0.00') as dep_amount1,
		IF((TRIM(d.acc2_bankrtno)!='' AND TRIM(d.acc2_bankacno)!=''),SUBSTR(d.acc2_type,1,1),'') as acct_type2,
		IF((TRIM(d.acc2_bankrtno)!='' AND TRIM(d.acc2_bankacno)!=''),TRIM(d.acc2_bankrtno),'') as transit_no2,
		IF((TRIM(d.acc2_bankrtno)!='' AND TRIM(d.acc2_bankacno)!=''),TRIM(d.acc2_bankacno),'') as account_no2,
		IF((TRIM(d.acc2_bankrtno)!='' AND TRIM(d.acc2_bankacno)!=''),IF(SUBSTR(d.acc2_payperiod,1,1)='A','F','P'),'') as dep_method2,
		IF((TRIM(d.acc2_bankrtno)!='' AND TRIM(d.acc2_bankacno)!=''),d.acc2_amt,'0.00') as dep_amount2,
		IF((TRIM(d.acc3_bankrtno)!='' AND TRIM(d.acc3_bankacno)!=''),SUBSTR(d.acc3_type,1,1),'') as acct_type3,
		IF((TRIM(d.acc3_bankrtno)!='' AND TRIM(d.acc3_bankacno)!=''),TRIM(d.acc3_bankrtno),'') as transit_no3,
		IF((TRIM(d.acc3_bankrtno)!='' AND TRIM(d.acc3_bankacno)!=''),TRIM(d.acc3_bankacno),'') as account_no3,
		IF((TRIM(d.acc3_bankrtno)!='' AND TRIM(d.acc3_bankacno)!=''),IF(SUBSTR(d.acc3_payperiod,1,1)='A','F','P'),'') as dep_method3,
		IF((TRIM(d.acc3_bankrtno)!='' AND TRIM(d.acc3_bankacno)!=''),d.acc3_amt,'0.00') as dep_amount3,
		'New' as PushStatus,
		'Included in Push -- New DD Info, 1st time pushing' as PushDescription
		FROM hrcon_deposit d
		LEFT JOIN emp_list e ON d.username=e.username AND d.ustatus='active' 
		LEFT JOIN mpHR_payData mp ON mp.AC_EmpID = e.sno 
		LEFT JOIN mpHR_payGroups mg ON mp.parid=mg.sno 
		LEFT JOIN hrcon_compen c ON e.username=c.username AND c.ustatus='active' 
		LEFT JOIN mpHR_locEmpInfo mphr ON e.sno=mphr.empsno AND mphr.locid=c.location 
		LEFT JOIN manage m ON d.delivery_method=m.sno 
		WHERE mp.employee_id!=0 AND m.type='deliverymethod' AND m.name='Direct Deposit' AND mg.parid='$batchid' GROUP BY e.sno
	";
	$bddres=mysql_query($bdd_que,$db);
	while($bddrow=mysql_fetch_assoc($bddres))
	{
		$bddrow=checkDirectDeposits($bddrow);
		$ibdque="INSERT INTO mpHR_deposits (parid,locid,empsno,employee_id,acct_type1,transit_no1,account_no1,dep_method1,dep_amount1,acct_type2,transit_no2,account_no2,dep_method2,dep_amount2,acct_type3,transit_no3,account_no3,dep_method3,dep_amount3,PushStatus,PushDescription,process,mpError) VALUES ('".$batchid."','".$bddrow['locid']."','".$bddrow['empsno']."','".$bddrow['employee_id']."','".$bddrow['acct_type1']."','".$bddrow['transit_no1']."','".$bddrow['account_no1']."','".$bddrow['dep_method1']."','".$bddrow['dep_amount1']."','".$bddrow['acct_type2']."','".$bddrow['transit_no2']."','".$bddrow['account_no2']."','".$bddrow['dep_method2']."','".$bddrow['dep_amount2']."','".$bddrow['acct_type3']."','".$bddrow['transit_no3']."','".$bddrow['account_no3']."','".$bddrow['dep_method3']."','".$bddrow['dep_amount3']."','".$bddrow['PushStatus']."','".$bddrow['PushDescription']."','N','')";
		mysql_query($ibdque,$db);
	}

	$payEmployees=$mphr->getACPayEmployees($batchid);
	if(count($payEmployees)>0)
	{
		for($j=0;$j<count($payEmployees);$j++)
		{
			if($dedCodes[$payEmployees[$j]['empsno']]!="")
			{
				$pque="SELECT DISTINCT(parid) as parid FROM mpHR_deductions WHERE type='D' AND process='Y' AND locid='".$payEmployees[$j]['locid']."' AND empsno='".$payEmployees[$j]['empsno']."' AND PushStatus!='Deleted' ORDER BY pdate DESC limit 1";
				$pres=mysql_query($pque,$db);
				$prow=mysql_fetch_assoc($pres);
				if(mysql_num_rows($pres)>0)
				{
					$pd_parid=$prow['parid'];
					$dique="INSERT INTO mpHR_deductions (parid,locid,empsno,employee_id,type,title,deduct_code,calc_freq,garn_method,amount,start_date,stop_date,ins_class,PushStatus,PushDescription,process,mpError) SELECT '$batchid',locid,empsno,employee_id,type,title,deduct_code,calc_freq,garn_method,amount,start_date,stop_date,ins_class,'Deleted' as PushStatus,'Not Included in Push -- Needs to delete manually' as PushDescription,'N','' FROM mpHR_deductions WHERE type='D' AND process='Y' AND locid='".$payEmployees[$j]['locid']."' AND empsno='".$payEmployees[$j]['empsno']."' AND parid='$pd_parid' AND PushStatus!='Deleted' AND deduct_code NOT IN ('".implode("','",$dedCodes[$payEmployees[$j]['empsno']])."')";
					mysql_query($dique,$db);
				}
			}

			if($garCodes[$payEmployees[$j]['empsno']]!="")
			{
				$pque="SELECT DISTINCT(parid) as parid FROM mpHR_deductions WHERE type='G' AND process='Y' AND locid='".$payEmployees[$j]['locid']."' AND empsno='".$payEmployees[$j]['empsno']."' AND PushStatus!='Deleted' ORDER BY pdate DESC limit 1";
				$pres=mysql_query($pque,$db);
				$prow=mysql_fetch_assoc($pres);
				if(mysql_num_rows($pres)>0)
				{
					$pg_parid=$prow['parid'];
					$gique="INSERT INTO mpHR_deductions (parid,locid,empsno,employee_id,type,title,deduct_code,calc_freq,garn_method,amount,start_date,stop_date,ins_class,garn_type,docket_no,date_issued,sdu_state,sdu_case_id,PushStatus,PushDescription,process,mpError) SELECT '$batchid',locid,empsno,employee_id,type,title,deduct_code,calc_freq,garn_method,amount,start_date,stop_date,ins_class,garn_type,docket_no,date_issued,sdu_state,sdu_case_id,'Deleted' as PushStatus,'Not Included in Push -- Needs to delete manually' as PushDescription,'N','' FROM mpHR_deductions WHERE type='G' AND process='Y' AND locid='".$payEmployees[$j]['locid']."' AND empsno='".$payEmployees[$j]['empsno']."' AND parid='$pg_parid' AND PushStatus!='Deleted' AND deduct_code NOT IN ('".implode("','",$garCodes[$payEmployees[$j]['empsno']])."')";
					mysql_query($gique,$db);
				}
			}
		}
	}
?>