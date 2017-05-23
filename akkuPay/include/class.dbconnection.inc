<?php
	class dbConnection
	{
	 	private $_db_user;
	 	private $_db_pass;
	 	private $_int_sysdb;
	 	private $_sysdb;
	 	private $_dbcon;
	 	private $_companyuser;
		public $compVersion;
	 	
	 	public $con_db;
	 	public $con_maindb;
		 	 	
		function __construct()
		{
			$this->_db_user = 'educeit';
			$this->_db_pass = 'educeit';
			$this->_int_sysdb = array("192.168.1.71");
		}
	
		function getActiveDatabase($companyuser,$connectionType)
		{
		 	$this->_companyuser = strtolower($companyuser);
			$this->_sysdb = $this->getActiveDatabaseConnection($this->_int_sysdb);
			$this->con_maindb = $this->_sysdb;
			
			if($connectionType == 'MainDB')
			{
				$this->getMainDBConnection();
				$dbConn = $this->con_maindb;
			}
			else
			{
				$dbConn = $this->setDatabaseConnection();				
			}
			return $dbConn;			
		}	
		
		function setDatabaseConnection()
		{
			if($this->_companyuser!="")
			{
	 			$this->getMainDBConnection();
	
				// Checking Company Availability 
				$dbg_que = "SELECT group_id, version FROM company_info,capp_info WHERE capp_info.sno=company_info.sno 
							AND company_info.status !='DA' AND comp_id='".$this->_companyuser."'";
				
				$dbg_res = mysql_query($dbg_que, $this->con_maindb);
		
				if(mysql_num_rows($dbg_res) > 0 )
				{
					$dbg_row=mysql_fetch_row($dbg_res); 
					$this->getAkkenSysDBConnection();
					$akken_db_group=$dbg_row[0];
					$this->compVersion = ($dbg_row[0] == 0 || $dbg_row[1] == '' || is_null($dbg_row[1])) ? 'alpha' : $dbg_row[1];
					$int_master_db = $this->getGroupDBIPS($akken_db_group,$this->_sysdb,"Master");
					
					$this->con_db=$this->getActiveDatabaseConnection($int_master_db);
					
					if($this->con_db != 600)
					{					 
						mysql_select_db($this->_companyuser,$this->con_db);
						$this->getMainDBConnection();
					}
				}			
				else
					 $this->con_db = 600;
			}
			
			return $this->con_db;
		}	
		
		function getActiveDatabaseConnection($int_sysdb_array)
		{
			$i=0;
		
			$this->_dbcon = mysql_connect($int_sysdb_array[$i],$this->_db_user,$this->_db_pass,0,65536);			
			if($this->_sysdb == "")
			{
				$this->_sysdb = $this->_dbcon;
				$this->getAkkenSysDBConnection();				
			}
			if(!$this->_dbcon)
			{
				$this->_dbcon = 600; // if connection failed will display error message in plugin, for this using code no 600
			}
			
			return $this->_dbcon;
		}
	
		function getAkkenSysDBConnection()	
		{
			mysql_select_db("akken_sysdb",$this->_sysdb);
		}
	
		function getMainDBConnection()	
		{
			mysql_select_db("iwnasp",$this->con_maindb);
		}
		
		function getGroupDBIPS($akken_group_id,$sysdb,$status="Master")
		{
			$i=0;
			$ips=array();
		
			$que="select int_ip from servers where group_id='".$akken_group_id."' AND status='".$status."' order by priority";
			$res=mysql_query($que,$sysdb) or die("fdfd");	
			while($row=mysql_fetch_row($res))
			{
				$ips[$i]=$row[0];
				$i++;
			}
			
			return $ips;
		}
	}	
?>
