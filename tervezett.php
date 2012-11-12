<?php 
include_once 'db.php';

if(array_key_exists('file',$_GET)) $file = $_GET['file']; else $file = "tervezett.graphml";
if(array_key_exists('checknodes',$_GET)) $checknodes = $_GET['checknodes']; else $checknodes = true;
if(array_key_exists('checkedges',$_GET)) $checkedges = $_GET['checkedges']; else $checkedges = true;
if(array_key_exists('tipus',$_GET)) $tipus = $_GET['tipus']; else $tipus = "lin";
if(array_key_exists('alap',$_GET)) $alap = $_GET['alap']; else $alap = 3;
if(array_key_exists('fok',$_GET)) $fok = $_GET['fok']; else $fok = 2;
if(array_key_exists('kihagy',$_GET)) $kihagy = $_GET['kihagy']; else $kihagy = 1;
if(array_key_exists('levon',$_GET)) $levon = $_GET['levon']; else $levon = 0;
if(array_key_exists('oszto',$_GET)) $oszto = $_GET['oszto']; else $oszto = 50;
if(array_key_exists('forcedmax',$_GET)) $forcedmax = $_GET['forcedmax']; else $forcedmax = 0;
if(array_key_exists('forcedmin',$_GET)) $forcedmin = $_GET['forcedmin']; else $forcedmin = 0;
if(array_key_exists('startdate',$_GET)) $startdate = $_GET['startdate']; else $startdate = date('Y-m-d',strtotime('-1 month -1 day'));
if(array_key_exists('enddate',$_GET)) $enddate = $_GET['enddate']; else	$enddate = date('Y-m-d',strtotime('-1 day'));
?>
<form name="input" action="tervezett.php" method="get">
file: <input type="text" name="file" value="<?php echo $file; ?>" /><br />
<br>
kezdő dátum:  <input type="text" name="startdate" value="<?php echo $startdate; ?>" /><br />
végső dátum:  <input type="text" name="enddate" value="<?php echo $enddate; ?>" /><br />
<br>
csomópontok frissítése:  <input type="checkbox" name="checknodes" value="1" <?php if($checknodes) echo "checked"; ?> /><br />
vonalak frissítése:  <input type="checkbox" name="checkedges" value="1" <?php if($checkedges) echo "checked"; ?> /><br />
<br>
méretezés alapja:  <input type="radio" name="tipus" value="log" <?php if($tipus == "log") echo "checked"; ?> /> logaritmikus<br />
méretezés alapja:  <input type="radio" name="tipus" value="lin" <?php if($tipus == "lin") echo "checked"; ?>" /> lineáris<br />
<br>
logaritmus alapja:  <input type="text" name="alap" value="<?php echo $alap; ?>" /><br />
lineáris osztólya:  <input type="text" name="oszto" value="<?php echo $oszto; ?>" /><br />
vonalak kihagyása:  <input type="text" name="kihagy" value="<?php echo $kihagy; ?>" /><br />
látogatás kihagyása:  <input type="text" name="levon" value="<?php echo $levon; ?>" /><br />
maximum méret:  <input type="text" name="forcedmax" value="<?php echo $forcedmax; ?>" /><br />
minimum méret:  <input type="text" name="forcedmin" value="<?php echo $forcedmin; ?>" /><br />
fokszám a méretnél:  <input type="text" name="fok" value="<?php echo $fok; ?>" /><br />


<input type="submit" value="Hajrá!" name="go" />
</form>
<?php 
if(!array_key_exists('go',$_GET)) exit;




if(file_exists("ganalytics_".$startdate."-".$enddate.".json")) {
	//echo"!"."ganalytics_".$startdate."-".$enddate.".json";
	db_query("UPDATE domains SET visits = 0;");
	db_query("TRUNCATE ga_source;");
	$json = json_decode(file_get_contents("ganalytics_".$startdate."-".$enddate.".json"),true);
	foreach($json['domains'] as $domain) {
		db_query("UPDATE domains SET visits = '".$domain['visits']."' WHERE id = ".$domain['id'].";");
	}
	foreach($json['ga_source'] as $source) {
		$tmp = array();
		foreach($source as $s) $tmp[] .= "'".$s."'";
		db_query("INSERT INTO ga_source VALUES (".implode(',',$tmp).");");
	}
} else {
	$_GET['startdate'] = $startdate;
	$_GET['enddate'] = $enddate;
	$file_tmp = $file;
	include 'helloanalytics.php';
	$file = $file_tmp;
}


//echo $file."<br>";
$source = file_get_contents($file);

	$pattern = '/<node id=\"n(.*?)\">(.*?)<data key=\"d4\"><\!\[CDATA\[(.*?)\]\]><\/data>(.*?)<y:Geometry height=\"(.*?)\" width=\"(.*?)\" x=\"(.*?)\" y=\"(.*?)\"(.*?)\/>(.*?)<\/node>/s';
	preg_match_all($pattern,$source,$results,PREG_SET_ORDER);
	$min = 1000; $max = 0;
	$nodes = array();
	foreach($results as $r) {
		$nodes[$r[3]] = array(
				'nid'=>$r[1],
				'w'=>$r[5],
				'h'=>$r[6],
				'x'=>$r[7],
				'y'=>$r[8],
				'id'=>$r[3]);
		if($r[5]<$min AND $r[5]>1) $min = $r[5];
		if($r[5]>$max) $max = $r[5];
	}

if($checknodes == true) {
	$domains = array();
	$results = db_query("SELECT * FROM domains WHERE ingraph <> 1 ORDER BY visits DESC");
	foreach($results as $r) {
		$domains[$r['id']] = $r;
	}
	
	$highest = 0;
	foreach($nodes as $node) {
		if(array_key_exists($node['id'],$domains)) {
			if($domains[$node['id']]['visits']> $highest) $highest = $domains[$node['id']]['visits'];
		} else unset($nodes[$node['id']]);
	}
	if($forcedmin) $min = $forcedmin;
	if($forcedmax) $max = $forcedmax;
	
	//echo "min: ".$min." max: ".$max." highest: ".$highest."<br>";
	$c = 0;
	foreach($nodes as $k=>$node) {
		
		if($domains[$k]['visits']<2) $domains[$k]['visits'] += 2;
	 	
		$size = $min + (($max-$min) / (log($highest)/log($domains[$k]['visits'])));
		$size = $min + (($max-$min) / (($highest)/($domains[$k]['visits'])));
		
		$size = sqrt((pow($min,2) + ((pow($max,2)-pow($min,2)) / ((pow($highest,2))/(pow($domains[$k]['visits'],2))))));
		
		$v = $domains[$k]['visits'];
		$lowest = 1;
		//$fok = $alap;
		$size =((pow($v,1/$fok)/pow($lowest,1/$fok)*$min)-$min)/(pow($highest,1/$fok)/pow($lowest,1/$fok)*$min-$min)*($max-$min)+$min;
		
		
		$x = $node['x']+($node['w']/2)-($size/2);
		$y = $node['y']+($node['h']/2)-($size/2);
		
		//echo $k.": ".$domains[$k]['visits']."=>".$node['w']."->".$size."<br>";	
		//echo $k.": ".$node['x']."/".$node['y']."=>".$x."->".$y."<br>";
		
		$pattern = '/<data key=\"d4\"><!\[CDATA\['.$k.'\]\]><\/data>(.*?)<y:Geometry height=\"(.*?)\" width=\"(.*?)\" x=\"(.*?)\" y=\"(.*?)\"(.*?)\/>(.*?)<\/node>/s';
	//	echo htmlentities($pattern)."<br>";
		$replacement = '<data key="d4"><![CDATA['.$k.']]></data>$1<y:Geometry height="'.$size.'" width="'.$size.'" x="'.$x.'" y="'.$y.'"$6/>$7</node>';
		$source = preg_replace($pattern, $replacement, $source,'-1',$count);
		$c += $count;
	}	
	//echo "<br>Csere történt: ".$c." db";
}

if($checkedges == true) {
	$pattern = '/<edge(.*)<\/edge>/s';
	$replacement = 'TEMPERTA';
	$source = preg_replace($pattern, $replacement, $source,'-1',$count);
	
	$text = '';
	
	
	$domains = db_query("SELECT * FROM domains WHERE ingraph <> 1 ORDER BY visits DESC");
	if($domains != 1) {
		$d = array();
		$resources = array();
		foreach($domains as $node) {
			if($node['tableId'] != '') $id = $node['tableId']; else $id = $node['domain'];
			$resources[$node['domain']] = array('visits'=>$node['visits'],'size'=>$size,'domain'=>$node['domain'],'id'=>$id);
			$d[] = $id;
		}
		/*edges*/
		$where = 'WHERE ';
		$from = ''; $to = ''; $c = 0;
		foreach($d as $node) {
			$c++;
			$from .= " 'from' = '".$node."'";
			$to .= " 'to' = '".$node."'";
			if($c<count($d)) {
				$from .= " OR "; $to .= " OR ";
			}
	
		}
		$where = "WHERE (".$from.") AND (".$to.")";
		$where = '';
		$edges = db_query("SELECT * FROM ga_source ".$where);
		if($edges != 1) {
			$c = 0;
			
			foreach($edges as $edge) {
				if(in_array($edge['from'],$d) AND in_array($edge['to'],$d) AND $edge['from'] != $edge['to']) {
					//echo $edge['from']."->".$edge['to'].": ".$edge['value']."<br>";

					
					if($levon != 0) { 
						if($edge['value'] <= $levon AND $edge['value'] != 0) $v = 0;
						else $v = $edge['value'] - $levon; }
					else $v = $edge['value'];
					
					if($tipus == 'log') $m = log($v+1,$alap)-$kihagy;
					elseif($tipus == "lin") $m = ($v+1)/$oszto;
					for($i=0;$i<$m;$i++) {
						$to = '';
						$results = db_query("SELECT * FROM domains WHERE tableId = '".$edge['to']."';");
						if($results != 1 ) {foreach($results as $r) {if(array_key_exists($r['id'],$nodes)) {$to = $nodes[$r['id']]['nid'];}}} 

						$from = '';
						$results = db_query("SELECT * FROM domains WHERE tableId = '".$edge['from']."' OR domain ='".$edge['to']."' ;");
						if($results != 1 AND $edge['from'] != 0) { 
							//print_R($results);
							foreach($results as $r) { if(array_key_exists($r['id'],$nodes)) { $from = $nodes[$r['id']]['nid']; }}}
							
						if($to AND $from) {
							
								
							//echo "$c--> from: ".$edge['from']."-".$from." to: ".$edge['to']."-".$to."<br>";
						$text  .=
						'<edge id="e'.$c.'" source="n'.$from.'" target="n'.$to.'">
							<data key="d10"><![CDATA['.$edge['value'].']]></data>
							<data key="d11">
								<y:PolyLineEdge>
								<y:Path sx="0.0" sy="0.0" tx="0.0" ty="0.0"/>
								<y:LineStyle color="#000000" type="line" width="1.0"/>
								<y:Arrows source="none" target="standard"/>
								<y:BendStyle smoothed="false"/>
								</y:PolyLineEdge>
							</data>
						</edge>';
						$c++;
						}
					}
				}
			}
	
		}
		//exit;
	}
	$source = preg_replace('/TEMPERTA/', $text, $source,'-1',$count);
	
}

    $tmp = explode('.',$file);
    unset($tmp[count($tmp)-1]);
    $graphml = implode('.',$tmp)."_".$startdate."-".$enddate.".graphml";
	file_put_contents($graphml, $source);

	
	die('Yeah! Sikerült: '.$graphml);
?>