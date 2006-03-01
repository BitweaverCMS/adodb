<?php

/**
  V4.70 06 Jan 2006  (c) 2000-2006 John Lim (jlim@natsoft.com.my). All rights reserved.
  Released under both BSD license and Lesser GPL library license. 
  Whenever there is any discrepancy between the two licenses, 
  the BSD license will take precedence.
	
  Set tabs to 4 for best viewing.
 
*/

class ADODB2_firebird extends ADODB_DataDict {
	
	var $databaseType = 'firebird';
	var $seqField = false;
	var $seqPrefix = 'S_';
	var $blobSize = 40000;	
 	
 	function ActualType($meta)
	{
		switch($meta) {
		case 'C': return 'VARCHAR';
		case 'XL':
		case 'X': return 'BLOB SUB_TYPE TEXT'; 
		
		case 'C2': return 'VARCHAR(32765)'; // up to 32K
		case 'X2': return 'VARCHAR(4096)';
		
		case 'B': return 'BLOB';
			
		case 'D': return 'DATE';
		case 'T': return 'TIMESTAMP';
		
		case 'L': return 'SMALLINT';
		case 'I': return 'INTEGER';
		case 'I1': return 'SMALLINT';
		case 'I2': return 'SMALLINT';
		case 'I4': return 'INTEGER';
		case 'I8': return 'INTEGER';
		
		case 'F': return 'DOUBLE PRECISION';
		case 'N': return 'DECIMAL';
		default:
			return $meta;
		}
	}
	
	function NameQuote($name = NULL)
	{
		if (!is_string($name)) {
			return FALSE;
		}
		
		$name = trim($name);
		
		if ( !is_object($this->connection) ) {
			return $name;
		}
		
		$quote = $this->connection->nameQuote;
		
		// if name is of the form `name`, quote it
		if ( preg_match('/^`(.+)`$/', $name, $matches) ) {
			return $quote . $matches[1] . $quote;
		}
		
		// if name contains special characters, quote it
		if ( !preg_match('/^[' . $this->nameRegex . ']+$/', $name) ) {
			return $quote . $name . $quote;
		}
		
		return $quote . $name . $quote;
	}

	function CreateDatabase($dbname, $options=false)
	{
		$options = $this->_Options($options);
		$sql = array();
		
		$sql[] = "DECLARE EXTERNAL FUNCTION LOWER CSTRING(80) RETURNS CSTRING(80) FREE_IT ENTRY_POINT 'IB_UDF_lower' MODULE_NAME 'ib_udf'";
		
		return $sql;
	}
	
	function _DropAutoIncrement($t)
	{
		if (strpos($t,'.') !== false) {
			$tarr = explode('.',$t);
			return 'DROP GENERATOR '.$tarr[0].'."G_'.$tarr[1].'"';
		}
		$t = substr($t, 1);
		return 'DROP GENERATOR "s_'.$t;
	}
	

	function _CreateSuffix($fname,$ftype,$fnotnull,$fdefault,$fautoinc,$fconstraint,$funsigned)
	{
		$suffix = '';
		
		if (strlen($fdefault)) $suffix .= " DEFAULT $fdefault";
		if ($fnotnull) $suffix .= ' NOT NULL';
		if ($fautoinc) $this->seqField = $fname;
		$fconstraint = preg_replace("/``/", "\"", $fconstraint);
		if ($fconstraint) $suffix .= ' '.$fconstraint;
		
		return $suffix;
	}
	
/*
CREATE or replace TRIGGER jaddress_insert
before insert on jaddress
for each row
begin
IF ( NEW."seqField" IS NULL OR NEW."seqField" = 0 ) THEN
  NEW."seqField" = GEN_ID("GEN_tabname", 1);
end;
*/
	function _Triggers($tabname,$tableoptions)
	{	
		if (!$this->seqField) return array();
		
		$tab1 = preg_replace( '/"/', '', $tabname );
		if ($this->schema) {
			$t = strpos($tab1,'.');
			if ($t !== false) $tab = substr($tab1,$t+1);
			else $tab = $tab1;
			$seqField = $this->seqField;
			$seqname = $this->schema.'.'.$this->seqPrefix.$tab;
			$trigname = $this->schema.'.T_'.$this->seqPrefix.$tab;
		} else {
			$seqField = $this->seqField;
			$seqname = $this->seqPrefix.$tab1;
			$trigname = 'T_'.$seqname;
		}
		if (isset($tableoptions['REPLACE']))
		{ $sql[] = "DROP GENERATOR \"$seqname\"";
		  $sql[] = "CREATE GENERATOR \"$seqname\"";
		  $sql[] = "ALTER TRIGGER \"$trigname\" BEFORE INSERT OR UPDATE AS BEGIN IF ( NEW.$seqField IS NULL OR NEW.$seqField = 0 ) THEN NEW.$seqField = GEN_ID(\"$seqname\", 1); END";
		}
		else if (isset($tableoptions['NEW']))
		{ $sql[] = "CREATE GENERATOR \"$seqname\"";
		  $sql[] = "CREATE TRIGGER \"$trigname\" FOR $tabname BEFORE INSERT OR UPDATE AS BEGIN IF ( NEW.$seqField IS NULL OR NEW.$seqField = 0 ) THEN NEW.$seqField = GEN_ID(\"$seqname\", 1); END";
		}
		
		$this->seqField = false;
		return $sql;
	}

}


?>