<?php 

include_once 'db.php';

// THIS IS ABSOLUTELY ESSENTIAL - DO NOT FORGET TO SET THIS 
@date_default_timezone_set("GMT"); 

$writer = new XMLWriter(); 
// Output directly to the user 

//$writer->openURI('php://output');
$writer->openMemory();
$writer->startDocument('1.0','UTF-8','no'); 

$writer->setIndent(4); 

$writer->startElement('graphml'); 
		$writer->writeAttribute('xsi:schemaLocation', 'http://graphml.graphdrawing.org/xmlns http://www.yworks.com/xml/schema/graphml/1.1/ygraphml.xsd');
		$writer->writeAttribute('xmlns','http://graphml.graphdrawing.org/xmlns');
        $writer->writeAttribute('xmlns:xsi','http://www.w3.org/2001/XMLSchema-instance');
        $writer->writeAttribute("xmlns:y","http://www.yworks.com/xml/graphml");
        $writer->writeAttribute("xmlns:yed","http://www.yworks.com/xml/yed/3");
        $writer->startElement('key');
        		$writer->writeAttribute('id','d0');
        		$writer->writeAttribute('for','node');
        		$writer->writeAttribute('attr.name','name');
        		$writer->writeAttribute('attr.type','string');
        $writer->endElement();
        
        $writer->startElement('key');
        $writer->writeAttribute('id','d10');
        $writer->writeAttribute('for','graphml');
        $writer->writeAttribute('yfiles.type','resources');
        $writer->endElement();
             
        $writer->startElement('key');
        	$writer->writeAttribute('id','d1');
        	$writer->writeAttribute('for','node');
        	$writer->writeAttribute('yfiles.type','nodegraphics');
        $writer->endElement();
      
        
        $writer->startElement('graph');
        $writer->writeAttribute('id','G');
        $writer->writeAttribute('edgedefault','directed');
        
        
        $domains = db_query("SELECT * FROM domains WHERE ingraph <> 1 ORDER BY visits DESC");
        //$domains = db_query("SELECT * FROM domains ");
        if($domains != 1) {
        	$d = array();
        	$max = 170; $min = 30; $highest = 0;
        	 $resources = array();
        	foreach($domains as $node) {
        		if($node['tableId'] != '') $id = $node['tableId']; else $id = $node['domain'];
        		$writer->startElement('node');
        		$writer->writeAttribute('id', $id);
        		$writer->writeAttribute('value', $id);
        		
        			$writer->startElement('data');
        			$writer->writeAttribute('key', 'd0');
        			$writer->text($node['domain']);
        			$writer->endElement();
            		
        			if($highest == 0) $highest = $node['visits'];
		
        			if($node['visits']<2) $node['visits'] += 2;
        			
        			$size = $min + (($max-$min) / (log($highest)/log($node['visits'])));
        			$size = $min + (($max-$min) / (($highest)/($node['visits'])));
        			        			     			
        			$size = sqrt((pow($min,2) + ((pow($max,2)-pow($min,2)) / ((pow($highest,2))/(pow($node['visits'],2))))));
        			
        			$resources[$node['domain']] = array('visits'=>$node['visits'],'size'=>$size,'domain'=>$node['domain'],'id'=>$id);
        			
        			/* r2 = gyök(r*r*%) */

        			label($writer,$node['domain'],$id,$size);
        			
        		$writer->endElement();

        		$d[] = $id;
        	}	
        	/*edges*/
        	$where = 'WHERE ';
        	$from = ''; $to = ''; $c = 0;
        	foreach($d as $node) {
        		$c++;
        		$from .= " 'from' = '".$node."'";
        		$to .= " 'to' = '".$node."'";
        		if($c<count($d)) {$from .= " OR "; $to .= " OR ";}
        		
        	}
        	$where = "WHERE (".$from.") AND (".$to.")";
        	$where = '';
        	$edges = db_query("SELECT * FROM ga_source ".$where);
        	if($edges != 1) {
        		$c = 0;
        		$alap = 5;
        		foreach($edges as $edge) {
        			if(in_array($edge['from'],$d) AND in_array($edge['to'],$d) AND $edge['from'] != $edge['to'] AND $edge['from']!=0) {
        				for($i=0;$i<(log($edge['value']+1,$alap));$i++) {
        				//for($i=0;$i<($edge['value']+1);$i++) {
        				$writer->startElement('edge');
        				$writer->writeAttribute('id', $c);
        				$writer->writeAttribute('source', $edge['from']);
        				$writer->writeAttribute('target', $edge['to']);
        				$writer->endElement();
        				$c++;
        				}
        			}
        		}
        		
        	} 
        	//exit;     
        }
        
        
     
        $writer->endElement();
        
        
        /*Resources*/
        $writer->startElement('data');
        	$writer->writeAttribute('key', 'd10');
        	$writer->startElement('y:Resources');
        	
        	foreach($resources as $resource) {
        		$writer->startElement('y:Resource');
        			$writer->writeAttribute('id', $resource['id']);
        			
        			$datas = db_query("SELECT * FROM ga_source WHERE `to` = '".$resource['id']."' AND (`from` = '(direct)' OR `from` = 'facebook.com' OR `from` = 'google') ORDER BY `from` DESC ");
        			$d = array('(direct)'=>'0','facebook.com'=>'0','google'=>'0','visits'=>$resource['visits']);
        			//$colors = array('(direct)'=>'brown','facebook.com'=>'blue','google'=>'yellow','visits'=>'green');
        			//$colors = array('(direct)'=>'#39E639','facebook.com'=>'#008500','google'=>'#269926','visits'=>'#00CC00');
        			//$colors = array('(direct)'=>'#A60000','facebook.com'=>'#FF7400','google'=>'#009999','visits'=>'#00CC00');
        			$colors = array('(direct)'=>'#793DD2','facebook.com'=>'#2ED097','google'=>'#FF7938','visits'=>'#FFEB38');
        			if($datas != 1) foreach($datas as $data) $d[$data['from']] = $data['value'];
        			//print_R($d);
        			$svg = '<svg xmlns="http://www.w3.org/2000/svg" version="1.1">';
        			
        			if($d['visits']<=2) $svg .= '<rect x="0" y="0" width="100" height="100" stroke-width="0" fill="red" />';
        			
        			else {
        				$max = "100";
        				$svg .= '<rect x="0" y="0" width="'.$max.'" height="'.$max.'" stroke-width="0" fill="green" />';
        				
        				arsort($d);
        				unset($d['(direct)']);
        				foreach($d as $k=>$i) {
        					if($i>0) {
        						$size = number_format(sqrt(pow($max,2)/($d['visits']/$i)),0);
        						$svg .= '<rect x="'.(($max-$size)/2).'" y="'.(($max-$size)/2).'" width="'.$size.'" height="'.$size.'" stroke-width="0" fill="'.$colors[$k].'" />';
        						//$svg .= '<text x="'.($max-).'" text-anchor="end" y="'.$size.'">'.$d['visits'].'</text>';
        					}
        				}
        			}
        			$svg .= '<text x="'.($max).'" text-anchor="end" y="'.$max.'">'.($d['visits']-2).'</text>';
        			
					$svg .= '</svg>';
        			
        			$writer->text($svg);
        		$writer->endElement();
        	}
        	
        	$writer->endElement();
        $writer->endElement();
       
        /*END of resources*/
        
 $writer->endElement(); 
//---------------------------------------------------- 
// End rss 
//$writer->endElement(); 
$writer->endDocument(); 

//$writer->flush(); 


//echo header('Content-type: text/xml');

//lets then echo our XML;

//echo $writer->outputMemory();
/* that is enough to display our dynamic XML in the browser,now if you wanna create a XML file we need to do this */
$filename = "example.graphml";
//lets output the memory to our file variable,and we gonna put that variable inside the file we gonna create
$file = $writer->outputMemory();
//lets create our file
file_put_contents($filename,$file);


function label($writer,$text,$id = '',$size = '30') {
	
	$writer->startElement('data');
		$writer->writeAttribute('key', 'd1');
		//$writer->startElement('y:ShapeNode');
		$writer->startElement('y:SVGNode');
		//$size = "30";
		$size = number_format($size,0);
			$writer->startElement('y:Geometry');
				$writer->writeAttribute('width',$size);
				$writer->writeAttribute('height',$size);
			$writer->endElement();
			
			$writer->startElement('y:Fill');
				if(is_numeric($id)) $writer->writeAttribute('color','#9FEE00');
				else $writer->writeAttribute('color','#FF0000');
				$writer->writeAttribute('transparent','false');
			$writer->endElement();
				
			$writer->startElement('y:NodeLabel');
				$writer->writeAttribute('a','b');
				$writer->text($text);
				
				//$writer->text('MACIHU');
				$tmp = '<y:LabelModel><y:SmartNodeLabelModel distance="4.0"/></y:LabelModel>';
				//$writer->text($tmp);
				$writer->text("\n");
				$writer->startElement('y:LabelModel');
					$writer->startElement('y:SmartNodeLabelModel');
						$writer->writeAttribute('distance','4.0');
					$writer->endElement();
				$writer->endElement();
			$writer->endElement();
			
			
			$writer->startElement('y:SVGNodeProperties');
				$writer->writeAttribute('usingVisualBounds','true');
			$writer->endElement();
			$writer->startElement('y:SVGModel');
					$writer->writeAttribute('svgBoundsPolicy','0');
					$writer->startElement('y:SVGContent');
						$writer->writeAttribute('refid',$id);
					$writer->endElement();
			$writer->endElement();			
			
		$writer->endElement();
	
	$writer->endElement();
	
	/*
	
	<y:NodeLabel alignment="center" autoSizePolicy="content" fontFamily="Dialog" fontSize="12" fontStyle="plain" hasBackgroundColor="false" hasLineColor="false" height="18.701171875" modelName="custom" textColor="#000000" visible="true" width="22.005859375" x="3.9970703125" y="5.6494140625">
	laci
		
		<y:ModelParameter>
			<y:SmartNodeLabelModelParameter labelRatioX="0.0" labelRatioY="0.0" nodeRatioX="0.0" nodeRatioY="0.0" offsetX="0.0" offsetY="0.0" upX="0.0" upY="-1.0"/>
		</y:ModelParameter>
	</y:NodeLabel>
	*/
	
}


?>