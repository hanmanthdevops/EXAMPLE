<?php
	function dataCheck($new,$old,$cfields)
	{
		$chkFlag=false;
		foreach($cfields as $key => $val)
		{
			if(trim($new[$val])!="")
			{
				$chkFlag=true;
				break;
			}
		}

		if($chkFlag)
		{
			foreach($cfields as $key => $val)
			{
				if(is_float($old[$val]) || is_float($new[$val]))
				{
					if(floatval($old[$val])!=floatval($new[$val]))
						return false;
				}
				else 
				{
					//print "dataCheck :: ".$old[$val]." :: $val :: ".$new[$val]." :: \n==============\n";
					if($old[$val]!=$new[$val])
						return false;
				}
			}
		}
		return $chkFlag;
	}

	function checkDeductions($nrow)
	{
		global $db,$dedCodes;

		$cfields = array("title","deduct_code","calc_freq","garn_method","amount","start_date","stop_date","ins_class");

		$oque="SELECT title,deduct_code,calc_freq,garn_method,amount,start_date,stop_date,ins_class,PushStatus FROM mpHR_deductions WHERE type='D' AND process!='N' AND locid='".$nrow['locid']."' AND empsno='".$nrow['empsno']."' AND deduct_code='".$nrow['deduct_code']."' ORDER BY pdate DESC limit 1";
		$ores=mysql_query($oque,$db);
		$orow=mysql_fetch_assoc($ores);
		if(mysql_num_rows($ores)>0 && $orow['PushStatus']!="Deleted")
		{
			$dedCodes[$nrow['empsno']][]=$nrow['deduct_code'];
			if(dataCheck($nrow,$orow,$cfields))
			{
				$nrow['PushStatus']="Old";
				$nrow['PushDescription']="Not included in Push -- No changes comparitive to last Push";
			}
			else
			{
				$nrow['PushStatus']="Conflict";
				$nrow['PushDescription']="Not included in Push -- Manually Correct in MPHR";
			}
		}

		return $nrow;
	}

	function checkGarnishments($nrow)
	{
		global $db,$garCodes;

		$cfields = array("title","deduct_code","calc_freq","garn_method","amount","start_date","stop_date","ins_class","garn_type","docket_no","date_issued","sdu_state","sdu_case_id");

		$oque="SELECT title,deduct_code,calc_freq,garn_method,amount,start_date,stop_date,ins_class,garn_type,docket_no,date_issued,sdu_state,sdu_case_id,PushStatus FROM mpHR_deductions WHERE type='G' AND process!='N' AND locid='".$nrow['locid']."' AND empsno='".$nrow['empsno']."' AND deduct_code='".$nrow['deduct_code']."' ORDER BY pdate DESC limit 1";
		$ores=mysql_query($oque,$db);
		$orow=mysql_fetch_assoc($ores);
		if(mysql_num_rows($ores)>0 && $orow['PushStatus']!="Deleted")
		{
			$garCodes[$nrow['empsno']][]=$nrow['deduct_code'];
			if(dataCheck($nrow,$orow,$cfields))
			{
				$nrow['PushStatus']="Old";
				$nrow['PushDescription']="Not included in Push -- No changes comparitive to last Push";
			}
			else
			{
				$nrow['PushStatus']="Conflict";
				$nrow['PushDescription']="Not included in Push -- Manually Correct in MPHR";
			}
		}

		return $nrow;
	}

	function checkDirectDeposits($nrow)
	{
		global $db;

		$cfields = array("acct_type1","transit_no1","account_no1","dep_method1","dep_amount1","acct_type2","transit_no2","account_no2","dep_method2","dep_amount2","acct_type3","transit_no3","account_no3","dep_method3","dep_amount3");

		$oque="SELECT acct_type1,transit_no1,account_no1,dep_method1,dep_amount1,acct_type2,transit_no2,account_no2,dep_method2,dep_amount2,acct_type3,transit_no3,account_no3,dep_method3,dep_amount3 FROM mpHR_deposits WHERE process!='N' AND locid='".$nrow['locid']."' AND empsno='".$nrow['empsno']."' ORDER BY pdate DESC limit 1";
		$ores=mysql_query($oque,$db);
		$orow=mysql_fetch_assoc($ores);
		if(mysql_num_rows($ores)>0)
		{
			if(dataCheck($nrow,$orow,$cfields))
			{
				$nrow['PushStatus']="Old";
				$nrow['PushDescription']="Not included in Push -- No changes comparitive to last Push";
			}
			else
			{
				$nrow['PushStatus']="Conflict";
				$nrow['PushDescription']="Not included in Push -- Manually Correct in MPHR";
			}
		}

		return $nrow;
	}
?>