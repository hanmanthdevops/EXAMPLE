<?php
class mpHR
{
	private $apihost = 'https://mypayspace.mypayrollhr.com/json/services/JsonService?wsdl';

	public $client = "";
	public $debug = false;
	public $token = "";
	public $bcode = "";
	public $res_error = "";
	public $synced = false;

	public $codeTypes = array('dept_code' => 'DP','div_code' => 'DV','proj_code' => 'PJ','pos_code' => 'PS','shift_code' => 'SH','ws_code' => 'WS','dedu_code' => 'DE','pay_code' => 'PY','pay_group_code' => 'PG','emp_status_code' => 'ES','emp_type_code' => 'ET');
	public $garnTypes = array('Banckruptcy' => 'B', 'Child Support' => 'S', 'Creditor Garnishment' => 'C', 'IRS Tax Levy' => 'I', 'State Tax Levy' => 'T');
	public $spAllowances = array();

	function __construct()
	{
		global $pri_production;

		if($pri_production)
			$this->apihost = 'https://mypayspace.mypayrollhr.com/json/services/JsonService?wsdl';

		$this->client = new soapclient($this->apihost, true);
	}

	function debug() 
	{
		if($this->debug)
			print $this->client->debug_string;

		return $this->client->debug_string;
	}

	function clear_debug() 
	{
		$this->client->debug_string="";
	}

	function msgDebug($msg) 
	{
		print $msg."\n";
	}

	function doAuth($data)
	{
		$result = $this->client->call('login',$data);
		if($this->client->getError())
		{
			print '======Error======\n'.$this->client->getError().'======\n';
			return false;
		}
		else
		{
			$auth=json_decode($result);
			if($auth['error_code'])
			{
				print '======Error======\n'.$auth['error_message'].'======\n';
				return false;
			}
			else
			{
				$this->token=$auth['session_id'];
				return $result;
			}
		}
	}

	function processRequest($data)
	{
		$mdata = array('sessionId' => $this->token, 'json' => json_encode($data));
		$this->client->debug_string.="\n==========REQUEST==========\n".json_encode($mdata);

		print "<PRE>";
		print_r($mdata);
		print "</PRE>";

		$result = json_decode($this->client->call('callSub',$mdata));

		if($this->client->getError())
			print "Error : ======\n".$this->client->getError()."\n======\n";
		else
			return $result;

		return false;
	}

	function processResponse($result)
	{
		if($result)
		{
			$this->client->debug_string.="\n==========RESPONSE==========\n".json_encode($result);

			print "<PRE>";
			print_r($result);
			print "</PRE>";

			if($result['error_code'])
			{
				$this->res_error = "Error : ".$result['error_code']."\n\n".$result['error_message']."\n";
				print "Error : ".$result['error_code']."\n======\n".$result['error_message']."======\n";
			}
			else
			{
				$this->res_error = "";
				return $result;
			}
		}
		return false;
	}

	function getTableHeaders($result)
	{
		return $result['table_data'][0];
	}

	function getTableData($result)
	{
		return array_slice($result['table_data'],1);
	}

	function formattedArray($codes)
	{
		$rdata = array();
		$headers = $this->getTableHeaders($codes);
		$data = $this->getTableData($codes);

		for($i=0;$i<count($data);$i++)
		{
			$rdata[$i][$headers['0']]=$data[$i][0];
			$rdata[$i][$headers['1']]=$data[$i][1];
		}

		return $rdata;
	}

	function dataDiff($m,$a)
	{
		$d=array();

		for($i=0;$i<count($a);$i++)
		{
			for($j=0;$j<count($m);$j++)
			{
				if($a[$i]['Code']==$m[$j]['Code'])
				{
					$d[$i]['Code']=$a[$i]['Code'];
					$d[$i]['Description']=$a[$i]['Description'];
					break;
				}
			}
		}

		return array_diff_key($a,$d);

	}

	/*************** READ FUNCTIONS ******************/

	function setStateFilingStatus($state)
	{
		global $maindb;

		$pque="SELECT code FROM mphr_state_allowances WHERE status='Y' AND state='$state'";
		$pres=mysql_query($pque,$maindb);
		while($prow=mysql_fetch_assoc($pres))
			$sfStatus[]=$prow['code'];

		return $sfStatus;
	}

	function setStatePrimaryAllowances()
	{
		global $maindb;

		$pque="SELECT state FROM mphr_primary_allowances WHERE status='Y'";
		$pres=mysql_query($pque,$maindb);
		while($prow=mysql_fetch_assoc($pres))
			$spAllowances[]=$prow['state'];

		return $spAllowances;
	}

	function getCodes($code_type)
	{
		$data = array('method_name' => 'WSP.GET.HRP.CODES', 'code_type' => $this->codeTypes[$code_type]);
		$result = $this->processRequest($data);
		return $this->processResponse($result);
	}

	function getDepartmentCodes()
	{
		return $this->getCodes('dept_code');
	}

	function getProjectCodes()
	{
		return $this->getCodes('proj_code');
	}

	function getPositionCodes()
	{
		return $this->getCodes('pos_code');
	}

	function getShiftCodes()
	{
		return $this->getCodes('shift_code');
	}

	function getWorksiteCodes()
	{
		return $this->getCodes('ws_code');
	}

	function getDeductionCodes()
	{
		return $this->getCodes('dedu_code');
	}

	function getPaygroupCodes()
	{
		return $this->getCodes('pay_group_code');
	}

	function getEmployeeStatusCodes()
	{
		return $this->getCodes('emp_status_code');
	}

	function getEmployeeTypeCodes()
	{
		return $this->getCodes('emp_type_code');
	}

	function getPayCodes()
	{
		return $this->getCodes('pay_code');
	}

	function getEmployees()
	{
		$data = array('method_name' => 'WSP.GET.HRP.EMPLOYEES');
		$result = $this->processRequest($data);
		return $this->processResponse($result);
	}

	//******** WRITE FUNCTIONS **********//

	function setCodes($data)
	{
		$data = array_merge(array('method_name' => 'WSP.ADD.HRP.CODE'),$data);
		$result = $this->processRequest($data);
		return $this->processResponse($result);
	}

	function addACCodes($data)
	{
		global $db;

		if($data['locid']=="")
			$data['locid']=0;

		$que="INSERT INTO mphr_codes (locid,type,code,description,city,state,zip_code,cdate,mdate,status) VALUES ('".$data['locid']."','".addslashes($data['code_type'])."','".addslashes($data['code_value'])."','".addslashes($data['code_description'])."','".addslashes($data['city'])."','".addslashes($data['state'])."','".addslashes($data['zip_code'])."',NOW(),NOW(),'Y')";
		if(mysql_query($que,$db))
			return true;
		else
			return false;
	}

	//******** PERSON FUNCTIONS **********//

	function updateMPHRCheck($locid,$acempNo,$mphrempNo)
	{
		global $db;

		$ique="INSERT INTO mpHR_locEmpInfo (locid,empsno,employee_id) VALUES ('$locid','$acempNo','$mphrempNo')";
		mysql_query($ique,$db);
	}

	function getPersonsData($locid)
	{
		global $db;

		$personsData=array();

		$pque="SELECT sno,locid,username,empsno,employee_id,first_name,middle_name,last_name,type_code,status_code,DATE_FORMAT(status_type_date,'%m/%d/%Y') as status_type_date,status_type_reason,position_code,DATE_FORMAT(position_date,'%m/%d/%Y') as position_date,position_reason,DATE_FORMAT(hire_date,'%m/%d/%Y') as hire_date,DATE_FORMAT(birth_date,'%m/%d/%Y') as birth_date,dept_code,worksite_code,shift_code,soc_sec_num,pay_method,pay_rate,DATE_FORMAT(pay_rate_date,'%m/%d/%Y') as pay_rate_date,pay_rate_reason,pay_period,pay_group,address_one,address_two,city,state,zip_code,geocode,division_code,dflt_pay_code,email_address,external_id,external_emp_id,gender,ethnicity,home_phone,mobile_phone,state_code1,state_filing_status1,state_allowances1,fed_tax_status,fed_tax_allows,fed_tax_override_type,fed_tax_override_amt,acStatus,cuser,cdate,muser,mdate FROM mpHR_personData WHERE locid='$locid' AND process='N' ORDER BY cdate";
		$pres=mysql_query($pque,$db);
		while($prow=mysql_fetch_assoc($pres))
			$personsData[]=$prow;

		return $personsData;
	}

	function updatePersonStatus($personData,$status)
	{
		global $db;

		if($status=="Y")
			$this->msgDebug("EMPLOYEE ==>> ".$personData['empsno']." :: ".$personData['first_name']." ".$personData['last_name']." :: Pushed Successfully");
		else if($status=="U")
			$this->msgDebug("EMPLOYEE ==>> ".$personData['empsno']." :: ".$personData['first_name']." ".$personData['last_name']." :: Updated Successfully");
		else if($status=="F")
			$this->msgDebug("EMPLOYEE ==>> ".$personData['empsno']." :: ".$personData['first_name']." ".$personData['last_name']." :: Push Failed");

		$uque="UPDATE mpHR_personData SET employee_id='".$personData['employee_id']."', process='$status', pdate=NOW(),mpError='".$this->res_error."',bcode='".addslashes($this->bcode)."' WHERE sno='".$personData['sno']."'";
		mysql_query($uque,$db);

		$ique="INSERT INTO mpHR_Log (type,parid,log,ltime) VALUES ('E','".$personData['sno']."','".addslashes($this->debug())."',NOW())";
		mysql_query($ique,$db);

		$this->clear_debug();
	}

	function lookupPerson($data,$lookfor)
	{
		$personData = array('ssn_flag' => 'Y', $lookfor => $data[$lookfor]);
		return $this->lookupPersonData($personData);
	}

	function lookupPersonData($data)
	{
		$data = array_merge(array('method_name' => 'WSP.GET.HRP.EMPLOYEE'),$data);
		$result = $this->processRequest($data);

		if($this->processResponse($result))
		{
			if($result["employee_id"]!="" && $result["employee_id"]>0)
				return $result;
			else
				return false;
		}
		return false;
	}

	function insertPerson($data)
	{
		$fields = array("employee_id" => "employee_id","external_id" => "external_id","external_emp_id" => "external_emp_id","first_name" => "first_name","middle_name" => "middle_name","last_name" => "last_name","type_code" => "type_code","status_code" => "status_code","status_type_date" => "status_type_date","status_type_reason" => "status_type_reason","position_code" => "position_code","position_date" => "position_date","hire_date" => "hire_date","birth_date" => "birth_date","dept_code" => "dept_code","worksite_code" => "worksite_code","shift_code" => "shift_code","soc_sec_num" => "soc_sec_num","pay_method" => "pay_method","pay_rate" => "pay_rate","pay_rate_date" => "pay_rate_date","pay_group" => "pay_group","address_one" => "address_one","address_two" => "address_two","city" => "city","state" => "state","zip_code" => "zip_code","division_code" => "division_code","dflt_pay_code" => "dflt_pay_code","email_address" => "email_address","external_id" => "external_id","external_emp_id" => "external_emp_id","gender" => "gender","ethnicity" => "ethnicity","home_phone" => "home_phone","mobile_phone" => "mobile_phone","fed_tax_status" => "fed_tax_status","fed_tax_allows" => "fed_tax_allows","fed_tax_override_type" => "fed_tax_override_type","fed_tax_override_amt" => "fed_tax_override_amt");

		if($data['state_code1']!="")
		{
			$filingStatus = $this->setStateFilingStatus($data['state_code1']);
			if($data['state_filing_status1']!="" && !in_array($data['state_filing_status1'],$filingStatus))
				$data['state_filing_status1']="";

			if(in_array($data['state_code1'],$this->spAllowances) || strtoupper($data['state_code1'])=="AZ")
				$data['state_allowances1']="";

			$fields = array("employee_id" => "employee_id","external_emp_id" => "external_emp_id","first_name" => "first_name","middle_name" => "middle_name","last_name" => "last_name","type_code" => "type_code","status_code" => "status_code","status_type_date" => "status_type_date","status_type_reason" => "status_type_reason","position_code" => "position_code","position_date" => "position_date","hire_date" => "hire_date","birth_date" => "birth_date","dept_code" => "dept_code","worksite_code" => "worksite_code","shift_code" => "shift_code","soc_sec_num" => "soc_sec_num","pay_method" => "pay_method","pay_rate" => "pay_rate","pay_rate_date" => "pay_rate_date","pay_group" => "pay_group","address_one" => "address_one","address_two" => "address_two","city" => "city","state" => "state","zip_code" => "zip_code","division_code" => "division_code","dflt_pay_code" => "dflt_pay_code","email_address" => "email_address","external_id" => "external_id","external_emp_id" => "external_emp_id","gender" => "gender","ethnicity" => "ethnicity","home_phone" => "home_phone","mobile_phone" => "mobile_phone","fed_tax_status" => "fed_tax_status","fed_tax_allows" => "fed_tax_allows","fed_tax_override_type" => "fed_tax_override_type","fed_tax_override_amt" => "fed_tax_override_amt","state_code1" => "state_code1","state_filing_status1" => "state_filing_status1","state_allowances1" => "state_allowances1");
		}

		foreach($fields as $key => $val)
			$personData[$val]=$data[$key];

		$mphrPersonData=$this->createPersonData($personData);
		if($mphrPersonData)
		{
			$data['employee_id']=$mphrPersonData['employee_id'];

			$this->updateMPHRCheck($data['locid'],$data['empsno'],$data['employee_id']);
			$this->updatePersonStatus($data,"Y");
			return true;
		}
		else
		{
			$this->updatePersonStatus($data,"F");
			return false;
		}
	}

	function createPersonData($data)
	{
		$data = array_merge(array('method_name' => 'WSP.ADD.HRP.EMPLOYEE'),$data);
		$result = $this->processRequest($data);
		return $this->processResponse($result);
	}

	function updatePerson($data)
	{
		$fields = array("employee_id" => "employee_id","external_id" => "external_id","external_emp_id" => "external_emp_id","first_name" => "first_name","middle_name" => "middle_name","last_name" => "last_name","type_code" => "type_code","status_code" => "status_code","status_type_date" => "status_type_date","status_type_reason" => "status_type_reason","position_code" => "position_code","position_date" => "position_date","hire_date" => "hire_date","birth_date" => "birth_date","dept_code" => "dept_code","shift_code" => "shift_code","soc_sec_num" => "soc_sec_num","pay_method" => "pay_method","pay_rate" => "pay_rate","pay_rate_date" => "pay_rate_date","pay_group" => "pay_group","address_one" => "address_one","address_two" => "address_two","city" => "city","state" => "state","zip_code" => "zip_code","division_code" => "division_code","dflt_pay_code" => "dflt_pay_code","email_address" => "email_address","external_id" => "external_id","external_emp_id" => "external_emp_id","gender" => "gender","ethnicity" => "ethnicity","home_phone" => "home_phone","mobile_phone" => "mobile_phone","fed_tax_status" => "fed_tax_status","fed_tax_allows" => "fed_tax_allows","fed_tax_override_type" => "fed_tax_override_type","fed_tax_override_amt" => "fed_tax_override_amt");

		if($data['state_code1']!="")
		{
			$filingStatus = $this->setStateFilingStatus($data['state_code1']);

			if($data['state_filing_status1']!="" && !in_array($data['state_filing_status1'],$filingStatus))
				$data['state_filing_status1']="";

			if(in_array($data['state_code1'],$this->spAllowances) || strtoupper($data['state_code1'])=="AZ")
				$data['state_allowances1']="";

			$fields = array("employee_id" => "employee_id","external_emp_id" => "external_emp_id","first_name" => "first_name","middle_name" => "middle_name","last_name" => "last_name","type_code" => "type_code","status_code" => "status_code","status_type_date" => "status_type_date","status_type_reason" => "status_type_reason","position_code" => "position_code","position_date" => "position_date","hire_date" => "hire_date","birth_date" => "birth_date","dept_code" => "dept_code","shift_code" => "shift_code","soc_sec_num" => "soc_sec_num","pay_method" => "pay_method","pay_rate" => "pay_rate","pay_rate_date" => "pay_rate_date","pay_group" => "pay_group","address_one" => "address_one","address_two" => "address_two","city" => "city","state" => "state","zip_code" => "zip_code","division_code" => "division_code","dflt_pay_code" => "dflt_pay_code","email_address" => "email_address","external_id" => "external_id","external_emp_id" => "external_emp_id","gender" => "gender","ethnicity" => "ethnicity","home_phone" => "home_phone","mobile_phone" => "mobile_phone","fed_tax_status" => "fed_tax_status","fed_tax_allows" => "fed_tax_allows","fed_tax_override_type" => "fed_tax_override_type","fed_tax_override_amt" => "fed_tax_override_amt","state_code1" => "state_code1","state_filing_status1" => "state_filing_status1","state_allowances1" => "state_allowances1");
		}

		foreach($fields as $key => $val)
			$personData[$val]=$data[$key];

		$mphrPersonData=$this->updatePersonData($personData);
		if($mphrPersonData)
		{
			$this->updatePersonStatus($data,"U");
			return true;
		}
		else
		{
			$this->updatePersonStatus($data,"F");
			return false;
		}
	}

	function updatePersonData($data)
	{
		$data = array_merge(array('method_name' => 'WSP.UPDATE.HRP.EMPLOYEE'),$data);
		$result = $this->processRequest($data);
		return $this->processResponse($result);
	}

	function getACDepts($locid)
	{
		global $db;

		$deptData=array();

		$que="SELECT d.depcode as Code, d.deptname as Description FROM department d LEFT JOIN contact_manage l ON d.loc_id=l.serial_no WHERE d.status='Active' AND l.country='243' AND TRIM(l.zipcode)!='' AND l.status!='BP' AND d.deflt='Y'";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_assoc($res))
			$deptData[]=$row;

		$que="SELECT d.depcode as Code, d.deptname as Description FROM department d LEFT JOIN contact_manage l ON d.loc_id=l.serial_no WHERE d.status='Active' AND l.country='243' AND TRIM(l.zipcode)!='' AND l.status!='BP' AND d.loc_id='$locid'";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_assoc($res))
			$deptData[]=$row;

		return $deptData;
	}

	function getACLocs($locid)
	{
		global $db;

		$locData=array();

		if($locid==0)
			$que="SELECT loccode as Code, heading as Description, city as City, state as State, zipcode as Zip_Code FROM contact_manage WHERE country='243' AND TRIM(zipcode)!='' AND status!='BP'";
		else
			$que="SELECT loccode as Code, heading as Description, city as City, state as State, zipcode as Zip_Code FROM contact_manage WHERE country='243' AND TRIM(zipcode)!='' AND status!='BP' AND serial_no='$locid'";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_assoc($res))
			$locData[]=$row;

		return $locData;
	}

	function getACShifts($locid)
	{
		global $db;

		$shiftData=array();

		$que="SELECT s.shiftcode as Code, s.shiftname as Description FROM shift_setup s LEFT JOIN hrcon_jobs j ON s.sno=j.shiftid LEFT JOIN emp_list e ON e.username=j.username LEFT JOIN hrcon_compen c ON e.username=c.username AND c.ustatus='active' LEFT JOIN contact_manage l ON l.serial_no=c.location  WHERE s.shiftcode!='' AND l.serial_no='$locid' GROUP BY s.sno";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_assoc($res))
			$shiftData[]=$row;

		return $shiftData;
	}

	function getACDeductions()
	{
		global $db;

		$deductionData=array();

		$que="SELECT code as Code, description as Description FROM mphr_codes WHERE type='DE'";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_assoc($res))
			$deductionData[]=$row;

		return $deductionData;
	}

	function getACPaygroups()
	{
		global $db;

		$paygroupData=array();

		$que="SELECT code as Code, description as Description FROM mphr_codes WHERE type='PG'";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_assoc($res))
			$paygroupData[]=$row;

		return $paygroupData;
	}

	function getACPayCodes()
	{
		global $db;

		$paycodeData=array();

		$que="SELECT code as Code, description as Description FROM mphr_codes WHERE type='PY'";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_assoc($res))
			$paycodeData[]=$row;

		return $paycodeData;
	}

	function getACProjects($parid)
	{
		global $db;

		$proData=array();

		$que="SELECT p.project_code as Code, p.AssignmentName as Description,IF((p.WorkState!='' && p.WorkZipCode!=''),p.worksite_code,'') as worksite_id FROM mpHR_payData p LEFT JOIN mpHR_payGroups g ON p.parid=g.sno WHERE TRIM(p.project_code)!='' AND g.parid='$parid' GROUP BY p.project_code";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_assoc($res))
			$proData[]=$row;

		return $proData;
	}

	function getACProjLocs($parid)
	{
		global $db;

		$locData=array();

		$que="SELECT p.worksite_code as Code, CONCAT(p.WorkState,'-',p.WorkZipCode,'-',p.WorkCity) as Description, TRIM(WorkCity) as City, TRIM(WorkState) as State, TRIM(WorkZipCode) as Zip_Code FROM mpHR_payData p LEFT JOIN mpHR_payGroups g ON p.parid=g.sno WHERE TRIM(p.WorkCity)!='' AND TRIM(p.WorkState)!='' AND TRIM(p.WorkZipCode)!='' AND g.parid='$parid' GROUP BY p.WorkState,p.WorkZipCode";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_assoc($res))
			$locData[]=$row;

		return $locData;
	}

	function syncDepartmentCodes($locid)
	{
		$m = $this->formattedArray($this->getDepartmentCodes());
		$a = $this->getACDepts($locid);
		$d = $this->dataDiff($m,$a);
		if(count($d)>0)
		{
			foreach($d as $key => $val)
			{
				$data = array('code_type' => 'DP', 'code_value' => $d[$key]['Code'], 'code_description' => $d[$key]['Description']);
				if($this->setCodes($data))
					print "DEPARTMENT CODE ==>> ".$data['code_value']." :: ".$data['code_description']." :: added successfully \n";
			}
		}
	}

	function syncLocationCodes($locid)
	{
		$m = $this->formattedArray($this->getWorkSiteCodes());
		$a = $this->getACLocs($locid);
		$d = $this->dataDiff($m,$a);
		if(count($d)>0)
		{
			foreach($d as $key => $val)
			{
				$data = array('code_type' => 'WS', 'code_value' => $d[$key]['Code'], 'code_description' => $d[$key]['Description'],'city' => $d[$key]['City'], 'state' => $d[$key]['State'], 'zip_code' => $d[$key]['Zip_Code']);
				if($this->setCodes($data))
					print "WORK SITE CODE ==>> ".$data['code_value']." :: ".$data['code_description']." :: added successfully \n";
			}
		}
	}

	function syncShiftCodes($locid)
	{
		$m = $this->formattedArray($this->getShiftCodes());
		$a = $this->getACShifts($locid);
		$d = $this->dataDiff($m,$a);
		if(count($d)>0)
		{
			foreach($d as $key => $val)
			{
				$data = array('code_type' => 'SH', 'code_value' => $d[$key]['Code'], 'code_description' => $d[$key]['Description']);
				if($this->setCodes($data))
					print "SHIFT CODE ==>> ".$data['code_value']." :: ".$data['code_description']." :: added successfully \n";
			}
		}
	}

	function syncDeductionCodes()
	{
		$m = $this->formattedArray($this->getDeductionCodes());
		$a = $this->getACDeductions();
		$d = $this->dataDiff($a,$m);
		if(count($d)>0)
		{
			foreach($d as $key => $val)
			{
				$data = array('code_type' => 'DE', 'code_value' => $d[$key]['Code'], 'code_description' => $d[$key]['Description'], 'city' => '', 'state' => '', 'zip_code' => '');
				if($this->addACCodes($data))
					print "DEDUCTION CODE ==>> ".$data['code_value']." :: ".$data['code_description']." :: synced successfully \n";
			}
		}
	}

	function syncPayCodes()
	{
		$m = $this->formattedArray($this->getPayCodes());
		$a = $this->getACPayCodes();
		$d = $this->dataDiff($a,$m);
		if(count($d)>0)
		{
			foreach($d as $key => $val)
			{
				$data = array('code_type' => 'PY', 'code_value' => $d[$key]['Code'], 'code_description' => $d[$key]['Description'], 'city' => '', 'state' => '', 'zip_code' => '');
				if($this->addACCodes($data))
					print "PAY CODE ==>> ".$data['code_value']." :: ".$data['code_description']." :: synced successfully \n";
			}
		}
	}


	function syncPayGroupCodes()
	{
		$m = $this->formattedArray($this->getPaygroupCodes());
		$a = $this->getACPaygroups();
		$d = $this->dataDiff($a,$m);
		if(count($d)>0)
		{
			foreach($d as $key => $val)
			{
				$data = array('code_type' => 'PG', 'code_value' => $d[$key]['Code'], 'code_description' => $d[$key]['Description'], 'city' => '', 'state' => '', 'zip_code' => '');
				if($this->addACCodes($data))
					print "PAY GROUP CODE ==>> ".$data['code_value']." :: ".$data['code_description']." :: synced successfully \n";
			}
		}
	}

	function syncProjectCodes($parid)
	{
		$m = $this->formattedArray($this->getProjectCodes());
		$a = $this->getACProjects($parid);
		$d = $this->dataDiff($m,$a);
		if(count($d)>0)
		{
			foreach($d as $key => $val)
			{
				$data = array('code_type' => 'PJ', 'code_value' => $d[$key]['Code'], 'code_description' => $d[$key]['Description'], 'worksite_id' => $d[$key]['worksite_id']);
				if($this->setCodes($data))
				{
					print "PROJECT CODE ==>> ".$data['code_value']." :: ".$data['code_description']." :: added successfully \n";
				}
				else
				{
					$data = array('code_type' => 'PJ', 'code_value' => $d[$key]['Code'], 'code_description' => $d[$key]['Description']);
					if($this->setCodes($data))
						print "PROJECT CODE ==>> ".$data['code_value']." :: ".$data['code_description']." :: added successfully \n";
				}
			}
		}
	}

	function syncProjectWorkSiteCodes($parid)
	{
		$m = $this->formattedArray($this->getWorkSiteCodes());
		$a = $this->getACProjLocs($parid);
		$d = $this->dataDiff($m,$a);
		if(count($d)>0)
		{
			foreach($d as $key => $val)
			{
				$data = array('code_type' => 'WS', 'code_value' => $d[$key]['Code'], 'code_description' => $d[$key]['Description'],'city' => $d[$key]['City'], 'state' => $d[$key]['State'], 'zip_code' => $d[$key]['Zip_Code']);
				if($this->setCodes($data))
					print "WORK SITE CODE ==>> ".$data['code_value']." :: ".$data['code_description']." :: added successfully \n";
			}
		}
	}

	function getACEmpWorkSites($parid)
	{
		global $db;

		$locData=array();

		$que="SELECT p.employee_id, p.worksite_code FROM mpHR_payData p LEFT JOIN mpHR_payGroups g ON p.parid=g.sno WHERE p.employee_id>0 AND p.worksite_code!='' AND p.AsgnCount=1 AND g.parid='$parid' GROUP BY p.employee_id";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_assoc($res))
			$locData[]=$row;

		return $locData;
	}

	function updateEmpWorkSiteStatus($personData,$status)
	{
		global $db;

		if($status=="U")
			$this->msgDebug("EMPLOYEE WORKSITE ==>> ".$personData['employee_id']." :: ".$personData['worksite_code']." :: Updated Successfully");
		else if($status=="F")
			$this->msgDebug("EMPLOYEE WORKSITE ==>> ".$personData['employee_id']." :: ".$personData['worksite_code']." :: Update Failed");

		//$ique="INSERT INTO mpHR_Log (type,parid,log,ltime) VALUES ('E','".$personData['sno']."','".addslashes($this->debug())."',NOW())";
		//mysql_query($ique,$db);

		$this->clear_debug();
	}

	function updateEmpWorkSiteCodes($parid)
	{
		$fields = array("employee_id" => "employee_id","worksite_code" => "worksite_code");
		$epw_data = $this->getACEmpWorkSites($parid);

		for($i=0;$i<count($epw_data);$i++)
		{
			$data=$epw_data[$i];
			foreach($fields as $key => $val)
				$personData[$val]=$data[$key];

			$mphrPersonData=$this->updatePersonData($personData);
			if($mphrPersonData)
				$this->updateEmpWorkSiteStatus($data,"U");
			else
				$this->updateEmpWorkSiteStatus($data,"F");
		}
	}

	function syncClientWorkSites($locid)
	{
		$m = $this->formattedArray($this->getWorkSiteCodes());
		$a = $this->getACLocs(0);
		$d = $this->dataDiff($a,$m);

		if(count($d)>0)
		{
			foreach($d as $key => $val)
			{
				$data = array('code_type' => 'WS', 'code_value' => $d[$key]['Code'], 'code_description' => $d[$key]['Description'], 'city' => $d[$key]['City'], 'state' => $d[$key]['State'], 'zip_code' => $d[$key]['Zip_Code'], 'locid' => $locid);
				if($this->addACCodes($data))
					print "CLIENT WORK SITE CODE ==>> ".$data['code_value']." :: ".$data['code_description']." :: synced successfully \n";
			}
		}
	}

	// PAYROLL PROCESSING FUNCTIONS

	function getACPayEmployees($parid)
	{
		global $db;

		$payEmployees=array();

		$pque="SELECT DISTINCT(mp.AC_EmpID) as empsno, mg.locid as locid FROM mpHR_payData mp LEFT JOIN mpHR_payGroups mg ON mp.parid=mg.sno WHERE mg.parid='$parid'";
		$pres=mysql_query($pque,$db);
		while($prow=mysql_fetch_assoc($pres))
			$payEmployees[]=$prow;

		return $payEmployees;
	}

	function updatePayBatchStatus($payBatch,$status)
	{
		global $companyuser, $db, $maindb;

		if($status=="Y" || $status=="P")
		{
			$uque="UPDATE mpHR_payUnitBatch SET process='$status',pdate=NOW() WHERE sno='".$payBatch['sno']."'";
			mysql_query($uque,$db);

			if($status=="P")
				$this->msgDebug("PAY BATCH ==>> ".$payBatch['sno']." :: ".$payBatch['paybname']." :: ".$payBatch['paydate']." :: Pushed - Partially");
			else
				$this->msgDebug("PAY BATCH ==>> ".$payBatch['sno']." :: ".$payBatch['paybname']." :: ".$payBatch['paydate']." :: Pushed Successfully");
		}
		else
		{
			$uque="UPDATE mpHR_payUnitBatch SET mpbnum='".$payBatch['mpbnum']."', paybcode='".$payBatch['paybcode']."', process='$status', pdate=NOW(),mpError='".$this->res_error."' WHERE sno='".$payBatch['sno']."'";
			mysql_query($uque,$db);

			if($status=="I")
				$this->msgDebug("PAY BATCH ==>> ".$payBatch['sno']." :: ".$payBatch['paybname']." :: ".$payBatch['paydate']." :: Initiated Successfully");
			else if($status=="F")
				$this->msgDebug("PAY BATCH ==>> ".$payBatch['sno']." :: ".$payBatch['paybname']." :: ".$payBatch['paydate']." :: Push Failed");

			$ique="INSERT INTO mpHR_Log (type,parid,log,ltime) VALUES ('B','".$payBatch['sno']."','".addslashes($this->debug())."',NOW())";
			mysql_query($ique,$db);

			$this->clear_debug();
		}

		$uque="UPDATE mpHR_Batches SET process='$status', pdate=NOW(), mpError='".$this->res_error."' WHERE comp_id='$companyuser' AND parid='".$payBatch['sno']."'";
		mysql_query($uque,$maindb);
	}

	function getPayBatches()
	{
		$data = array('method_name' => 'WSP.GET.HRP.BATCHES');
		$result = $this->processRequest($data);
		return $this->processResponse($result);
	}

	function getACPayBatches($locid,$batchid)
	{
		global $db;

		$batchData=array();

		$pque="SELECT b.sno,b.paybtype,b.paybname,DATE_FORMAT(b.paydate,'%m/%d/%Y') as paydate FROM mpHR_payUnitBatch b LEFT JOIN mpHR_payGroups g ON b.sno=g.parid WHERE b.process='N' AND b.paybcode='' AND b.mpbnum=0 AND g.locid='$locid' AND b.sno='$batchid' GROUP BY b.sno ORDER BY b.cdate";
		$pres=mysql_query($pque,$db);
		while($prow=mysql_fetch_assoc($pres))
			$batchData[]=$prow;

		return $batchData;
	}

	function getACBatchPayGroups($parid)
	{
		global $db;

		$groupData=array();

		$gque="SELECT sno,payunit,DATE_FORMAT(paysdate,'%m/%d/%Y') as paysdate,DATE_FORMAT(payedate,'%m/%d/%Y') as payedate,wworked,dedperiod FROM mpHR_payGroups WHERE parid='".$parid."' ORDER BY sno";
		$gres=mysql_query($gque,$db);
		while($grow=mysql_fetch_assoc($gres))
			$groupData[]=$grow;

		return $groupData;
	}


	function addBatch($payBatch)
	{
		$payBatch['paybcode']=$this->bcode;
		$bdata = array('pay_date' => $payBatch['paydate'], 'description' => $payBatch['paybname']);

		$payGroups=$this->getACBatchPayGroups($payBatch['sno']);
		for($i=0;$i<count($payGroups);$i++)
		{
			$j=$i+1;
			$gdata = array('pay_group'.$j => $payGroups[$i]['payunit'], 'pd_start_date'.$j => $payGroups[$i]['paysdate'], 'pd_end_date'.$j => $payGroups[$i]['payedate'], 'weeks_worked'.$j => $payGroups[$i]['wworked'], 'deduction_pd'.$j => $payGroups[$i]['dedperiod']);
			$bdata = array_merge($bdata,$gdata);
		}

		$data = array_merge(array('method_name' => 'WSP.ADD.HRP.BATCH'),$bdata);
		$result = $this->processRequest($data);
		if($this->processResponse($result))
		{
			$payBatch['mpbnum']=$result['batch_no'];
			$this->updatePayBatchStatus($payBatch,"I");
			return $payBatch;
		}
		else
		{
			$payBatch['mpbnum']=0;
			$this->updatePayBatchStatus($payBatch,"F");
			return false;
		}
	}

	function updatePayBatchGrossAmount($payBatch)
	{
		global $db;

		$parid=$payBatch['sno'];

		$saque="SELECT mp.parid, SUM(mp.hrs_units * mp.pay_rate) as gpamount FROM mpHR_payData mp LEFT JOIN mpHR_payGroups mg ON mg.sno=mp.parid WHERE mp.process='Y' AND mg.parid='$parid' GROUP BY mp.parid";
		$sares=mysql_query($saque,$db);
		while($sarow=mysql_fetch_row($sares))
		{
			$grpid=$sarow[0];

			$uaque="UPDATE mpHR_payGroups SET gpamount='".$sarow[1]."' WHERE sno='$grpid'";
			mysql_query($uaque,$db);
		}

		$saque="SELECT SUM(mp.hrs_units * mp.pay_rate) as gpamount FROM mpHR_payData mp LEFT JOIN mpHR_payGroups mg ON mg.sno=mp.parid WHERE mp.process='Y' AND mg.parid='$parid'";
		$sares=mysql_query($saque,$db);
		$sarow=mysql_fetch_row($sares);

		$uaque="UPDATE mpHR_payUnitBatch SET gpamount='".$sarow[0]."' WHERE sno='$parid'";
		mysql_query($uaque,$db);
	}

	function updatePayDataStatus($payData,$status)
	{
		global $db;

		if($status=="Y")
		{
			$this->msgDebug("PAY DATA ==>> ".$payData['AC_EmpID']." :: ".$payData['employee_id']." :: ".$payData['charge_date']." :: Pushed Successfully");
			$mphr_flag="Y";
		}
		else if($status=="F")
		{
			$this->msgDebug("PAY DATA ==>> ".$payData['AC_EmpID']." :: ".$payData['employee_id']." :: ".$payData['charge_date']." :: Push Failed");
			$mphr_flag="N";
		}

		if($payData['RefType']=="Time")
		{
			if($status=="Y")
				$tuque="UPDATE timesheet_hours SET payroll='".$payData['batch_number']."',mphr='$mphr_flag',mphr_time=NOW() WHERE sno IN ('".str_replace(",","','",$payData['RefID'])."')";
			else
				$tuque="UPDATE timesheet_hours SET mphr='$mphr_flag',mphr_time=NOW() WHERE sno IN ('".str_replace(",","','",$payData['RefID'])."')";
			mysql_query($tuque,$db);
		}

		if($payData['RefType']=="Expense")
		{
			if($status=="Y")
				$euque="UPDATE expense SET payroll='".$payData['batch_number']."',mphr='$mphr_flag',mphr_time=NOW() WHERE sno='".$payData['RefID']."'";
			else
				$euque="UPDATE expense SET mphr='$mphr_flag',mphr_time=NOW() WHERE sno='".$payData['RefID']."'";
			mysql_query($euque,$db);
		}

		$uque="UPDATE mpHR_payData SET batch_number='".$payData['batch_number']."',paybcode='".$payData['paybcode']."',process='$status', pdate=NOW(), mpError='".addslashes($this->res_error)."' WHERE sno='".$payData['sno']."'";
		mysql_query($uque,$db);

		if($payData['employee_id']>0)
		{
			$ique="INSERT INTO mpHR_Log (type,parid,log,ltime) VALUES ('T','".$payData['sno']."','".addslashes($this->debug())."',NOW())";
			mysql_query($ique,$db);
		}

		$this->clear_debug();
	}

	function getACPayData($parid,$batch_number,$paybcode)
	{
		global $db;

		$uque="UPDATE mpHR_payData mp, emp_list e, mpHR_payGroups mg, mpHR_locEmpInfo mphr, hrcon_compen hc SET mp.employee_id=mphr.employee_id WHERE mp.AC_EmpID=e.sno AND mp.employee_id=0 AND mp.parid=mg.sno AND hc.username=e.username AND hc.ustatus='active' AND mphr.empsno=e.sno AND mphr.locid=hc.location AND mg.parid='$parid'";
		mysql_query($uque,$db);

		$payData=array();

		$pque="SELECT mp.sno,mp.parid,mp.AC_EmpID,mp.FirstName,mp.LastName,mp.employee_id,DATE_FORMAT(mp.charge_date,'%m/%d/%Y') as charge_date,mp.pay_code,mp.hrs_units,mp.pay_rate,mp.position_code,mp.division_code,mp.dept_code,mp.shift_code,mp.project_code,'$batch_number' AS batch_number,'$paybcode' AS paybcode,mp.RefID,mp.RefType FROM mpHR_payData mp LEFT JOIN mpHR_payGroups mg ON mp.parid=mg.sno WHERE mp.process='N' AND mp.paybcode='' AND mg.parid='$parid'";
		$pres=mysql_query($pque,$db);
		while($prow=mysql_fetch_assoc($pres))
			$payData[]=$prow;

		return $payData;
	}

	function addPayData($pdata)
	{
		if($pdata['employee_id']==0)
		{
			$this->res_error="New Employee Creation Failed. Please check the Employee Data Report for Errors.";
			$this->updatePayDataStatus($pdata,"F");
			return false;
		}
		else
		{
			$hdata = "employee_id".chr(252)."charge_date".chr(252)."pay_code".chr(252)."hrs_units".chr(252)."pay_rate".chr(252)."position_code".chr(252)."division_code".chr(252)."dept_code".chr(252)."shift_code".chr(252)."project_code".chr(252)."batch_number".chr(253);
			$rdata = $pdata['employee_id'].chr(252).$pdata['charge_date'].chr(252).$pdata['pay_code'].chr(252).$pdata['hrs_units'].chr(252).$pdata['pay_rate'].chr(252).$pdata['position_code'].chr(252).$pdata['division_code'].chr(252).$pdata['dept_code'].chr(252).$pdata['shift_code'].chr(252).$pdata['project_code'].chr(252).$pdata['batch_number'];
	
			$data = array('table_data' => $hdata.$rdata);
			$data = array_merge(array('method_name' => 'WSP.ADD.HRP.PAYDATA'),$data);
			$result = $this->processRequest($data);
			if($this->processResponse($result))
			{
				$this->updatePayDataStatus($pdata,"Y");
				return true;
			}
			else
			{
				$this->updatePayDataStatus($pdata,"F");
				return false;
			}
		}
	}

	function addQuickPayData($payData)
	{
		$rdata="";
		$hdata = "employee_id".chr(252)."external_emp_id".chr(252)."pay_code".chr(252)."charge_date".chr(252)."hrs_units".chr(252)."pay_rate".chr(252)."shift_code".chr(252)."division_code".chr(252)."dept_code".chr(252)."project_code".chr(253);
		for($j=0;$j<count($payData);$j++)
		{
			$pdata=$payData[$j];
			$pdata['external_emp_id']="";

			$rdata.=$pdata['employee_id'].chr(252).$pdata['external_emp_id'].chr(252).$pdata['pay_code'].chr(252).$pdata['charge_date'].chr(252).$pdata['hrs_units'].chr(252).$pdata['pay_rate'].chr(252).$pdata['shift_code'].chr(252).$pdata['division_code'].chr(252).$pdata['dept_code'].chr(252).$pdata['project_code'];
			if($j<count($payData)-1)
				$rdata.=chr(253);
		}

		return  $hdata.$rdata;
	}

	function addQuickBatch($payBatch,$rdata)
	{
		$payGroups=$this->getACBatchPayGroups($payBatch['sno']);

		$bdata = array('check_date' => $payBatch['paydate'], 'period_start_date' => $payGroups[0]['paysdate'], 'period_end_date' => $payGroups[0]['payedate'], 'deduct_period' => $payGroups[0]['dedperiod'], 'weeks_worked' => $payGroups[0]['wworked'], 'table_data' => $rdata);
		$data = array_merge(array('method_name' => 'WSP.POST.QUICKPAY'),$bdata);
		$result = $this->processRequest($data);

		if($this->processResponse($result))
		{
			$payBatch['mpbnum']=$result['batch_no'];
			$this->updatePayBatchStatus($payBatch,"I");
			$this->updateQuickPayDataStatus($payBatch);
			$this->updatePayBatchStatus($payBatch,"Y");
			return $payBatch;
		}
		else
		{
			$payBatch['mpbnum']=0;
			$this->updatePayBatchStatus($payBatch,"F");
			return false;
		}
	}

	function updateQuickPayDataStatus($payBatch)
	{
		global $db;

		$sque="SELECT p.RefID FROM mpHR_payData p, mpHR_payGroups g WHERE p.RefType='Time' AND p.parid=g.sno AND g.parid='".$payBatch['sno']."'";
		$sres=mysql_query($sque,$db);
		while($srow=mysql_fetch_row($sres))
		{
			$tuque="UPDATE timesheet_hours t, mpHR_payData p, mpHR_payGroups g SET t.payroll='".$payBatch['mpbnum']."',t.mphr='Y',t.mphr_time=NOW() WHERE t.sno IN ('".str_replace(",","','",$srow[0])."') AND p.RefType='Time' AND p.parid=g.sno AND g.parid='".$payBatch['sno']."'";
			mysql_query($tuque,$db);
		}

		$euque="UPDATE expense e, mpHR_payData p, mpHR_payGroups g SET e.payroll='".$payBatch['mpbnum']."',e.mphr_time=NOW() WHERE e.sno=p.RefID AND p.RefType='Expense' AND p.parid=g.sno AND g.parid='".$payBatch['sno']."'";
		mysql_query($euque,$db);

		$puque="UPDATE mpHR_payData p, mpHR_payGroups g SET p.process='Y', p.pdate=NOW() WHERE p.parid=g.sno AND g.parid='".$payBatch['sno']."'";
		mysql_query($puque,$db);
	}

	// DEDUCTION AND GARNISHMENTS FUNCTIONS

	function getACPayDeductions($parid,$batch_number,$bcode)
	{
		global $db;

		$sque="UPDATE mpHR_deductions mp, emp_list e, hrcon_compen hc, mpHR_locEmpInfo mphr SET mp.employee_id=mphr.employee_id WHERE mp.empsno=e.sno AND hc.username=e.username AND hc.ustatus='active' AND mphr.empsno=e.sno AND mphr.locid=hc.location AND mp.employee_id=0 AND mp.parid='$parid' AND mp.type='D'";
		mysql_query($sque,$db);

		$sque="UPDATE mpHR_deductions SET pdate=NOW(),process='Y',bcode='$bcode' WHERE type='D' AND parid='$parid' AND process='N' AND PushStatus!='New'";
		mysql_query($sque,$db);

		$payDeductions=array();

		$pque="SELECT sno,parid,empsno,employee_id,deduct_code,calc_freq,garn_method,amount,start_date,stop_date,'$batch_number' AS batch_number,'$bcode' AS bcode FROM mpHR_deductions WHERE parid='$parid' AND process='N' AND bcode='' AND type='D'";
		$pres=mysql_query($pque,$db);
		while($prow=mysql_fetch_assoc($pres))
			$payDeductions[]=$prow;

		return $payDeductions;
	}

	function addEmpDeduction($ddata)
	{
		$data = array('employee_id' => $ddata['employee_id'], 'deduct_code' => $ddata['deduct_code'], 'calc_freq' => $ddata['calc_freq'], 'garn_method' => $ddata['garn_method'], 'amount' => $ddata['amount'], 'start_date' => $ddata['start_date'], 'stop_date' => $ddata['stop_date']);
		$data = array_merge(array('method_name' => 'WSP.ADD.EMP.DEDUCTION'),$data);
		$result = $this->processRequest($data);
		if($this->processResponse($result))
		{
			$this->updateDeductionStatus($ddata,"Y");
			return true;
		}
		else
		{
			$this->updateDeductionStatus($ddata,"F");
			return false;
		}
	}

	function getACPayGarnishments($parid,$batch_number,$bcode)
	{
		global $db;

		$sque="UPDATE mpHR_deductions mp, emp_list e, hrcon_compen hc, mpHR_locEmpInfo mphr SET mp.employee_id=mphr.employee_id WHERE mp.empsno=e.sno AND hc.username=e.username AND hc.ustatus='active' AND mphr.empsno=e.sno AND mphr.locid=hc.location AND mp.employee_id=0 AND mp.parid='$parid' AND mp.type='G'";
		mysql_query($sque,$db);

		$sque="UPDATE mpHR_deductions SET pdate=NOW(),process='Y',bcode='$bcode' WHERE type='G' AND parid='$parid' AND process='N' AND PushStatus!='New'";
		mysql_query($sque,$db);

		$payGarnishments=array();

		$pque="SELECT sno,parid,empsno,employee_id,deduct_code,calc_freq,garn_method,amount,start_date,stop_date,garn_type,docket_no,date_issued,sdu_state,sdu_case_id,'$batch_number' AS batch_number,'$bcode' AS bcode FROM mpHR_deductions WHERE parid='$parid' AND process='N' AND bcode='' AND type='G'";
		$pres=mysql_query($pque,$db);
		while($prow=mysql_fetch_assoc($pres))
			$payGarnishments[]=$prow;

		return $payGarnishments;
	}

	function addEmpGarnishment($ddata)
	{
		/*
		if($this->garnTypes[$ddata['garn_type']]=="S")
			$data = array('employee_id' => $ddata['employee_id'], 'deduct_code' => $ddata['deduct_code'], 'calc_freq' => $ddata['calc_freq'], 'garn_method' => $ddata['garn_method'], 'amount' => $ddata['amount'], 'start_date' => $ddata['start_date'], 'stop_date' => $ddata['stop_date'], 'garn_type' => $this->garnTypes[$ddata['garn_type']], 'docket_no' => $ddata['docket_no'], 'date_issued' => $ddata['date_issued'], 'sdu_state' => $ddata['sdu_state'], 'sdu_case_id' => $ddata['sdu_case_id']);
		else
			$data = array('employee_id' => $ddata['employee_id'], 'deduct_code' => $ddata['deduct_code'], 'calc_freq' => $ddata['calc_freq'], 'garn_method' => $ddata['garn_method'], 'amount' => $ddata['amount'], 'start_date' => $ddata['start_date'], 'stop_date' => $ddata['stop_date'], 'garn_type' => $this->garnTypes[$ddata['garn_type']], 'docket_no' => $ddata['docket_no'], 'date_issued' => $ddata['date_issued'], 'sdu_case_id' => $ddata['sdu_case_id']);
		*/

		$data = array('employee_id' => $ddata['employee_id'], 'deduct_code' => $ddata['deduct_code'], 'calc_freq' => $ddata['calc_freq'], 'garn_method' => $ddata['garn_method'], 'amount' => $ddata['amount'], 'start_date' => $ddata['start_date'], 'stop_date' => $ddata['stop_date'], 'garn_type' => $this->garnTypes[$ddata['garn_type']], 'docket_no' => $ddata['docket_no'], 'date_issued' => $ddata['date_issued'], 'sdu_state' => $ddata['sdu_state'], 'sdu_case_id' => $ddata['sdu_case_id']);

		$data = array_merge(array('method_name' => 'WSP.ADD.EMP.GARNISHMENT'),$data);
		$result = $this->processRequest($data);
		if($this->processResponse($result))
		{
			$this->updateDeductionStatus($ddata,"Y");
			return true;
		}
		else
		{
			$this->updateDeductionStatus($ddata,"F");
			return false;
		}
	}

	function updateDeductionStatus($dedData,$status)
	{
		global $db;

		if($status=="Y")
			$this->msgDebug("DEDUCTIONS / GARNISHMENTS ==>> ".$dedData['empsno']." :: ".$dedData['employee_id']." :: ".$dedData['deduct_code']." :: Pushed Successfully");
		else if($status=="F")
			$this->msgDebug("DEDUCTIONS / GARNISHMENTS ==>> ".$dedData['empsno']." :: ".$dedData['employee_id']." :: ".$dedData['deduct_code']." :: Push Failed");

		$uque="UPDATE mpHR_deductions SET bcode='".$dedData['bcode']."',process='$status', pdate=NOW(), mpError='".addslashes($this->res_error)."' WHERE sno='".$dedData['sno']."'";
		mysql_query($uque,$db);

		$ique="INSERT INTO mpHR_Log (type,parid,log,ltime) VALUES ('G','".$dedData['sno']."','".addslashes($this->debug())."',NOW())";
		mysql_query($ique,$db);

		$this->clear_debug();
	}

	// DIRECT DEPOSITS FUNCTIONS

	function getACPayDeposits($parid,$batch_number,$bcode)
	{
		global $db;

		$sque="UPDATE mpHR_deposits mp, emp_list e, hrcon_compen hc, mpHR_locEmpInfo mphr SET mp.employee_id=mphr.employee_id WHERE mp.empsno=e.sno AND hc.username=e.username AND hc.ustatus='active' AND mphr.empsno=e.sno AND mphr.locid=hc.location AND mp.employee_id=0 AND mp.parid='$parid'";
		mysql_query($sque,$db);

		$sque="UPDATE mpHR_deposits SET pdate=NOW(),process='Y',bcode='$bcode' WHERE parid='$parid' AND process='N' AND PushStatus!='New'";
		mysql_query($sque,$db);

		$payDeposits=array();

		$pque="SELECT sno,parid,empsno,employee_id,acct_type1,transit_no1,account_no1,dep_method1,dep_amount1,acct_type2,transit_no2,account_no2,dep_method2,dep_amount2,acct_type3,transit_no3,account_no3,dep_method3,dep_amount3,'$batch_number' AS batch_number,'$bcode' AS bcode FROM mpHR_deposits WHERE parid='$parid' AND process='N' AND bcode='' AND PushStatus='New'";
		$pres=mysql_query($pque,$db);
		while($prow=mysql_fetch_assoc($pres))
			$payDeposits[]=$prow;

		return $payDeposits;
	}

	function addEmpDeposit($ddata)
	{
		$data = array('employee_id' => $ddata['employee_id'], 'acct_type1' => $ddata['acct_type1'], 'transit_no1' => $ddata['transit_no1'], 'account_no1' => $ddata['account_no1'], 'dep_method1' => $ddata['dep_method1'], 'dep_amount1' => $ddata['dep_amount1'], 'acct_type2' => $ddata['acct_type2'], 'transit_no2' => $ddata['transit_no2'], 'account_no2' => $ddata['account_no2'], 'dep_method2' => $ddata['dep_method2'], 'dep_amount2' => $ddata['dep_amount2'],'acct_type3' => $ddata['acct_type3'], 'transit_no3' => $ddata['transit_no3'], 'account_no3' => $ddata['account_no3'], 'dep_method3' => $ddata['dep_method3'], 'dep_amount3' => $ddata['dep_amount3']);
		$data = array_merge(array('method_name' => 'WSP.UPDATE.HRP.EMPLOYEE'),$data);
		$result = $this->processRequest($data);
		if($this->processResponse($result))
		{
			$this->updateDepositStatus($ddata,"Y");
			return true;
		}
		else
		{
			$this->updateDepositStatus($ddata,"F");
			return false;
		}
	}

	function updateDepositStatus($depData,$status)
	{
		global $db;

		if($status=="Y")
			$this->msgDebug("DIRECT DEPOSIT ==>> ".$depData['empsno']." :: ".$depData['employee_id']." :: ".$depData['transit_no1']." :: Pushed Successfully");
		else if($status=="F")
			$this->msgDebug("DIRECT DEPOSIT ==>> ".$depData['empsno']." :: ".$depData['employee_id']." :: ".$depData['transit_no1']." :: Push Failed");

		$uque="UPDATE mpHR_deposits SET bcode='".$depData['bcode']."',process='$status', pdate=NOW(), mpError='".addslashes($this->res_error)."' WHERE sno='".$depData['sno']."'";
		mysql_query($uque,$db);

		$ique="INSERT INTO mpHR_Log (type,parid,log,ltime) VALUES ('D','".$depData['sno']."','".addslashes($this->debug())."',NOW())";
		mysql_query($ique,$db);

		$this->clear_debug();
	}

	function doESS_SSO()
	{
		$data = array('web_user_id' => 'rc.vempati@gmail.com', 'web_company_number' => '44', 'employee_id' => '199');
		$data = array_merge(array('method_name' => 'WSP.SSO.INIT'),$data);
		$result = $this->processRequest($data);
		print_r($result);
	}
}
?>