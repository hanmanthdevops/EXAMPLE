<?php
	// Domain name for Product
	$egdomain="akken.com";

	// Default A record value for mail servers
	$akken_arec_mail="smtp";
	$akken_arec_port="25";

	// Production or Development checking
	$pri_production=true;

	// SYSDB IP Addresses
	$int_sysdb = array("192.168.1.71","192.168.1.72");

	// Database Servers Username and Password
	$db_user="educeit";
	$db_pass="educeit";

	// Set True for drivers False in Application
	$pri_driver=true;

	// Version of companies
	$default_version="beta";
	$version_clause=" AND company_info.version='$default_version'";
?>