<?php
/**
 * Show logs from a file that matches a regular expression. Matches multiline records.
 * @param string $search [optional] A string or regular expression to match; default = ""
 * @param boolean $isregexp [optional] $search is a regular expression; default = false
 * @param boolean $matchcase [optional] Match $search case; default = false
 * @param string $order [optional] Order records by date ASC or DESC; default = "DESC"
 * @param datetime $startdate [optional] Only search for records after this date 
 * @param datetime $enddate [optional] Only search for records before this date
 * @param string $recordBeginning [optional] A regular expression for the beginning of a record in the file; default = "/^\[(?P<datetime>\d\d-\w\w\w-\d\d\d\d \d\d:\d\d:\d\d[^\]]*)\]/"
 * @return array An associative array with keys: total, pattern, records. Each record is an associative array with keys: number, time, record.
 */
function searchLog($search = null, $isregexp = false, $matchcase = false, $order = "DESC", $startdate = null, $enddate = null, $recordBeginning = null) {
	$file = ini_get('error_log');
	//turn off error reporting so we aren't writing error to the file.
	error_reporting(0);
	if (is_file($file)) {
		//set recordBeginning
		if ($recordBeginning === null) {
			$recordBeginning = "/^\[(?P<datetime>\d\d-\w\w\w-\d\d\d\d \d\d:\d\d:\d\d[^\]]*)\]/";
		}
		//check startdate and enddate
		if (!empty($startdate) && is_string($startdate)) {
			$startdate = strtotime($startdate);
		} else {
			$startdate = null;
		}
		if (!empty($enddate) && is_string($enddate)) {
			$enddate = strtotime($enddate);
		} else {
			$enddate = null;
		}
		//get pattern to match
		$terms = array();
		if ($search === null || $search === "") {
			$pattern = "/.*/s";
		} else if ($isregexp) {
			$pattern = "/" . str_replace("/", "\/", $search) . "/s";
		} else {
			$pattern = "/^";
			$terms = str_getcsv($search, ' ');
			foreach ($terms as $key => $term) {
				if ($term === "") {
					unset($terms[$key]);
				} else {
					if (strpos($term, "-") === 0 && strpos($search, "\"{$term}") === false) {
						$term = substr($term, 1);
						$pattern .= "(?!.*" . preg_quote($term, "/") . ")";
						unset($terms[$key]);
						$terms[-($key + 1)] = $term;
					} else {
						$pattern .= "(?=.*" . preg_quote($term, "/") . ")";
					}
				}
			}
			$pattern .= ".*$/s";
		}
		if (!$matchcase) {
			$pattern .= "i";
		}

		$matchedRecords = array();
		$recordNumber = 0;
		$lastRecordTime = null;
		$record = null;
		$handle = fopen($file, "r");
		if ($handle) {
			while (($buffer = fgets($handle)) !== false) {
				//check if this is the beginning of a new record.
				$matches = null;
				if (preg_match($recordBeginning, $buffer, $matches)) {
					$recordNumber++;
					//search $record
					if ($record !== null && ($startdate === null || $lastRecordTime > $startdate) && ($enddate === null || $lastRecordTime < $enddate) && preg_match($pattern, $record)) {
						if ($order === "ASC") {
							$matchedRecords[] = array(
								"number" => $recordNumber,
								"time" => $lastRecordTime,
								"record" => $record,
							);
						} else {
							array_unshift($matchedRecords, array(
								"number" => $recordNumber,
								"time" => $lastRecordTime,
								"record" => $record,
							));
						}
					}
					$lastRecordTime = isset($matches["datetime"]) ? strtotime($matches["datetime"]) : null;
					$record = $buffer;
				} else {
					$record .= $buffer;
				}
			}
			//search last record
			if (($startdate === null || $lastRecordTime > $startdate) && ($enddate === null || $lastRecordTime < $enddate) && preg_match($pattern, $record)) {
				if ($order === "ASC") {
					$matchedRecords[] = [
						"number" => $recordNumber,
						"time" => $lastRecordTime,
						"record" => $record,
					];
				} else {
					array_unshift($matchedRecords, [
						"number" => $recordNumber,
						"time" => $lastRecordTime,
						"record" => $record,
					]);
				}
			}
			fclose($handle);
		}
		return [
			"total" => $recordNumber,
			"terms" => $terms,
			"pattern" => $pattern,
			"records" => $matchedRecords,
		];
	} else {
		return null;
	}
}

if (count($_GET) === 0) {
	header("location: ?startdate=" . urlencode(date("m/d/Y") . " 12:00 am"));
}
$set_time_limit = set_time_limit(30);

$search = filter_input(INPUT_GET, "search");
$isregex = filter_input(INPUT_GET, "isregex", FILTER_VALIDATE_BOOLEAN);
$matchcase = filter_input(INPUT_GET, "matchcase", FILTER_VALIDATE_BOOLEAN);
$reverseorder = filter_input(INPUT_GET, "reverseorder", FILTER_VALIDATE_BOOLEAN);
$startdate = filter_input(INPUT_GET, "startdate");
$enddate = filter_input(INPUT_GET, "enddate");
$log = searchLog($search, $isregex, $matchcase, ($reverseorder ? "ASC" : "DESC"), $startdate, $enddate);

$recordsHTML = null;
if ($log !== null) {
	$recordsHTML = count($log["records"]) . " matches found in {$log["total"]} records\n\n";
	$termsPattern = false;
	if ($isregex) {
		$termsPattern = $log["pattern"];
	} else {
		$terms = [];
		foreach ($log["terms"] as $key => $term) {
			if ($key >= 0) {
				$terms[] = preg_quote(filter_var($term, FILTER_SANITIZE_STRING), "/");
			}
		}
		if (count($terms) > 0) {
			$termsPattern = "/" . implode("|", $terms) . "/s";
			if (!$matchcase) {
				$termsPattern .= "i";
			}
		}
	}
	//$recordsHTML .= "pattern: {$log["pattern"]}\n";
	//$recordsHTML .= "termspattern: {$termsPattern}\n";
	//$recordsHTML .= "terms: " . print_r($log["terms"], true) . "\n\n";
	foreach ($log["records"] as $record) {
		$recordsHTML .= ($termsPattern ? preg_replace($termsPattern, "<span class='found'>$0</span>", filter_var($record["record"], FILTER_SANITIZE_STRING)) : filter_var($record["record"], FILTER_SANITIZE_STRING));
	}
}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Search Error Log</title>
		<style>
			html, body{
				padding: 0;
				margin: 0;
			}
			#form{
				width: 100%;
				border-bottom: 1px solid #000;
				background: #fff;
			}
			#form #breadcrumbs{
				float: right;
			}
			#form #breadcrumbs a{
				text-decoration: none;
				color: #000;
			}
			#form div{
				display: inline-block;
				margin: 10px;
				vertical-align: middle;
			}
			#form #sticky{
				float: right;
			}
			#spacer{
				visibility: hidden;
				display: none;
			}
			#records{
				white-space: pre;
				font-family: monospace;
			}
			#records .found {
				color: #f00;
				font-weight: bold;
			}
		</style>
	</head>
	<body>
		<div id="form">
			<div id="sticky">
				<input type="checkbox" checked title="Sticky Header" tabindex="-1" />
			</div>
			<form action="" method="get">
				<div>
					<label for="search">Search:</label>
					<input type="text" name="search" id="search" value="<?= filter_var($search, FILTER_SANITIZE_STRING) ?>" />
				</div>
				<div>
					<input type="checkbox" name="isregex" value="1" id="isregex"<?= $isregex ? " checked" : "" ?> /><label for="isregex">Regular Expression</label><br/>
					<input type="checkbox" name="matchcase" value="1" id="matchcase"<?= $matchcase ? " checked" : "" ?> /><label for="matchcase">Match Case</label><br/>
					<input type="checkbox" name="reverseorder" value="1" id="reverseorder"<?= $reverseorder ? " checked" : "" ?> /><label for="reverseorder">Reverse Order</label>
				</div>
				<div>
					<label for="startdate">Start Date:</label>
					<input type="datetime-local" name="startdate" id="startdate" value="<?= filter_var($startdate, FILTER_SANITIZE_STRING) ?>" />
				</div>
				<div>
					<label for="enddate">End Date:</label>
					<input type="datetime-local" name="enddate" id="enddate" value="<?= filter_var($enddate, FILTER_SANITIZE_STRING) ?>" />
				</div>
				<div>
					<input type="submit" value="Search" />
				</div>
			</form>
		</div>
		<div id="spacer"></div>
		<div id="records"><?= $recordsHTML ?></div>
		<script>
			document.querySelector("#sticky input").onchange = function () {
				document.querySelector("#form").style.position = (this.checked ? "fixed" : "absolute");
			};
			window.onresize = function () {
				document.querySelector("#spacer").style.height = document.querySelector("#form").offsetHeight + "px";
			};
			window.onresize();
			document.querySelector("#form").style.position = "fixed";
			document.querySelector("#spacer").style.display = "block";
		</script>
	</body>
</html>
