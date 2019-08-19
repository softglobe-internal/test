<?php

#$conn = new mysqli( "localhost", "root", "mysqlroot" ) or die( 'Connection failed : ' . $conn->connect_error() );
#$conn->select_db( "koha_library", $conn );

$basepath = str_replace("/examples", "", dirname(__FILE__));
//$uri = $_SERVER['REQUEST_URI'];
function get_url_prefix() {
	return "http://".$_SERVER["HTTP_HOST"];
}
define( "BASE_PATH", $basepath );
define( "NO_OF_ROWS", 5 );
define( "NO_OF_COLS", 5 );
define( "CARD_WIDTH", 89 );
define( "CARD_HEIGHT", 59 );
define( "DEFAULT_X_BG", 6 );
define( "DEFAULT_Y_BG", 5 );

function show($array = array()) {
	echo "<pre>";
	print_r($array);
	echo "</pre>";
	die;
}

function get_db_conn() {
	//$conn = $conn->connect( "localhost", "root", "mysqlroot" ) or die( $conn->error() );
	/*$conn = new mysqli( "localhost", "root", "mysqlroot" ) or die( 'Connection failed : ' . $conn->connect_error() );
	$conn->select_db( "koha_library", $conn );
	$res = $conn->query('SELECT * FROM `borrowers`');*/
	$db = new PDO("mysql:host=localhost;dbname=koha_library", 'root', "mysqlroot");
	$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	//$statement = $db->prepare("select * from borrowers");
	//$statement->execute();
	//$row = $statement->fetch();
	//show($row);
	return $db;
}


function get_row_by_five_columns( $allrows = array() ) {
	$numrows = count($allrows);
	if( ! $numrows ) return array();

	$recordsByPages = array();
	$memInfoRows = array();
	$recno = 0;
	$colno = 0;
	$colinfo = array();
	$ii = 0;
	
	foreach( $allrows as $row ) {
		$ii++;
		if( $numrows == $ii ) {
			$colinfo[] = $row;
			$memInfoRows[] = $colinfo;
			$colinfo = array();
			$colno = 0;
		} elseif( $colno < 4 ) {
			$colinfo[] = $row;
			$colno++;
		} else {
			$colinfo[] = $row;
			$memInfoRows[] = $colinfo;
			$colinfo = array();
			$colno = 0;
		}
	}
	
	$pageRecors = array();
	$pageRows 	= array();
	$rowIndex = 0;
	$indexNos = count($memInfoRows) - 1;
	$page_no = 0;
	$newpage=1;
	//echo "<pre>";
	//echo count($memInfoRows);
	//exit;
	foreach( $memInfoRows as $index => $col ) {
		$pageRows[$rowIndex++] = $col;
		/*if( ($index > 0) && ($rowIndex == 5) ) {
			$rowIndex = 0;
			$pageRecors[$page_no] = $pageRows;
			$page_no++;
		} elseif( $index == 0 ) {
			$pageRecors[$page_no][] = $col;
		} elseif($indexNos!=0) {
			$pageRecors[$page_no++][] = $col;
		}*/
		if($index < 5){
			$pageRecors[$page_no][] = $col;
		}else{
			$pageRecors[$newpage][] = $col;
			if(fmod($index,5)==4){
				$newpage++;
			}			
		}
	}
	return $pageRecors;
}


function get_members($conn) {	
	$cardnumbers = isset( $_REQUEST[ "cardnumbers" ] ) ? $_REQUEST[ "cardnumbers" ] : "";
	$cardnumbers = ( $cardnumbers != "" ) ? explode( "\n", $cardnumbers ) : array();
	
	if( empty($cardnumbers) ) {
		header( "location:/cgi-bin/koha/vvs/reports/quick_report.pl?reports=member_card_printing" );
		exit;
	}

	$card_numbers = "";
	foreach( $cardnumbers as $cnum ) $card_numbers .= "'".trim($cnum)."',";
	$card_numbers = trim($card_numbers, ",");

	$query = "SELECT brr.borrowernumber, brr.cardnumber, CONCAT(brr.firstname, ' ', brr.surname) AS member_name, 
	brr.dateexpiry, brr.signature, brrimg.mimetype, brrimg.imagefile 
	FROM borrowers AS brr 
	LEFT JOIN patronimage AS brrimg ON brrimg.borrowernumber = brr.borrowernumber 
	WHERE brr.cardnumber IN (".$card_numbers.") 
	ORDER BY brr.cardnumber";
#die($query);
	$path_vvs_reports = str_replace( "/tcpdf", "", BASE_PATH );
	//require_once ( $path_vvs_reports . "/connection.php" );
	$result = $conn->prepare($query);
	$result->execute();
	$members = $result->fetchAll();

	if( ! count($members) ) {
		header( "location:/cgi-bin/koha/vvs/reports/quick_report.pl?reports=member_card_printing" );
		exit;
	}
	return $members;
}



$action = isset( $_REQUEST[ "action" ] ) ? $_REQUEST[ "action" ] : "";
$pageRecors = array();
if ( $action == "search_member" ) {
	$conn = get_db_conn();
	$pageRecors = get_row_by_five_columns(get_members($conn));
}
if( empty( $pageRecors ) ) {
	header( "location:/cgi-bin/koha/vvs/reports/quick_report.pl?reports=member_card_printing" );
	exit;
}


require_once( BASE_PATH . '/examples/tcpdf_include.php');
require_once( BASE_PATH . '/tcpdf_barcodes_1d.php');

$custom_layout 	= array(457.2, 304.8); // in mm
$pdf 		= new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, $custom_layout, true, 'UTF-8', false);

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor( "Via Vitae Solutions" );
$pdf->SetTitle( "Member Card PDF" );
$pdf->SetSubject( "Multiple member library cads" );
$pdf->SetKeywords( "Via Vitae Solutions, Member Card, PDF, Sandip Kapat" );

// set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set margins
$pdf->SetMargins(5, 5, 5);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
//$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetFooterMargin(5);

// set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 0);

// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// set some language-dependent strings (optional)
if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
	require_once(dirname(__FILE__).'/lang/eng.php');
	$pdf->setLanguageArray($l);
}

// set default font subsetting mode
$pdf->setFontSubsetting(true);

// Set font
//$pdf->SetFont('dejavusans', '', 9, '', true);
$pdf->SetFont('freesansb', '', 9);

// Add a page
//$pdf->AddPage("L");

// set JPEG quality
$pdf->setJPEGQuality(300);

// Image example with resizing
$card_background = get_url_prefix()."/intranet-tmpl/prog/img/Membership-Card.jpg";
$background_x_axis 	= 6;
$background_y_axis 	= 5;
$width 		= 88.9;
$height 		= 58.9;
$image_type = "JPG";
$link 		= '';
$align 		= '';
$resize 	= true;
$dpi 		= 300;
$palign 	= '';
$ismask 	= false;
$imgmask 	= false;
$border 	= 0;
$fitbox 	= false;
$hidden 	= false;
$fitonpage 	= false;


$profile_pic = get_url_prefix()."/intranet-tmpl/prog/img/patron-blank.png";

$pdf->setCellHeightRatio(2);
// Set some content to print

$imagepath = str_replace("cgi-bin/vvs/reports/tcpdf", "htdocs/intranet-tmpl/prog/img/vvsimages", BASE_PATH);
$table = "";

foreach( $pageRecors as $page => $memInfoRows ) {

	$pdf->AddPage("L");
	$table = "<table class=\"main\">";

foreach( $memInfoRows as $tr => $rows ) {
//for( $tr = 0; $tr < NO_OF_ROWS; $tr++ ) {
	$table .= "<tr>";
	$background_x_axis 	= 0;
	$profile_pic_x_axis	= 3;
	$barcode_x_axis 	= 5;
	$barcode_y_axis 	= 0;
	$sign_x_asis 		= 0;
	$sign_y_asis 		= 0;
	$i = 0;
	foreach( $rows as $index => $row ) {
	//for( $index = 1; $index <= NO_OF_COLS; $index++ ) {
		$i++;
		
		$member_id 	= $row['borrowernumber'];
		$member_name 	= $row['member_name'];
		$cardnumber 	= "{$row['cardnumber']}";
		$dateexpiry 	= $row['dateexpiry'];
		$expiryDateArr = ( $dateexpiry != "" ) ? explode( "-", $dateexpiry ) : array();
		$dateexpiry = (count($expiryDateArr) == 3) ? $expiryDateArr[2].'/'.$expiryDateArr[1].'/'.$expiryDateArr[0] : "";
		
		$imagefile 	= $row['imagefile'];
		$signature 	= $row['signature'];
		$signature 	= 'signature-'.$member_id.'.jpg';
		// Background
		$background_x_axis 	+= ( $i > 1 ) ? CARD_WIDTH : 6;
		$background_y_axis = 5 + (CARD_HEIGHT*$tr);
		$pdf->Image(
			$card_background, $background_x_axis, $background_y_axis, 
			$width, $height, $image_type, $link, $align, $resize, $dpi, 
			$palign, $ismask, $imgmask, $border, $fitbox, $hidden, $fitonpage
		);
		// End
		
		// Profile Picture
		$profile_picture = ( $imagefile != "" ) ? '@'.base64_decode(base64_encode($imagefile)) : $profile_pic;
		$profile_pic_x_axis += ( $i > 1 ) ? CARD_WIDTH : 7;
		$profile_pic_y_axis = 23.8 + (CARD_HEIGHT*$tr);
		$pdf->Image($profile_picture, $profile_pic_x_axis, $profile_pic_y_axis, 17, 18, 'PNG', '', '', true, 300, '', false, false, 0, false, false, false);
		// End
		
		
		// Barcode
		//$cardnumber 	= 'RKMSM/HIST/2016/32000100745';
		//$cardnumber 	= '100000000000000000000000000000000000000000';
		$barcodeobj 	= new TCPDFBarcode($cardnumber, 'C39');
		$imgdata 	= $barcodeobj->getVVSBarcodePNG(2, 40, array(0,0,0));
		$barcode_x_axis += ( $i > 1 ) ? CARD_WIDTH : 8;
		$barcode_y_axis = 56 + (CARD_HEIGHT*$tr);
		//$barcode_width = (strlen($cardnumber) < 10) ? $barcodeWidths[strlen($cardnumber)] : 80;
		$barcode_width = 15;
		$card_length = strlen($cardnumber);
		if( $card_length >= 3 && $card_length <= 6 ) $barcode_width = 30;
		if( $card_length >= 7 && $card_length <= 20 ) $barcode_width = 35;
		if( $card_length >= 11 && $card_length <= 15 ) $barcode_width = 70;
		if( $card_length >= 16 && $card_length <= 18 ) $barcode_width = 75;
		if( $card_length >= 19 && $card_length <= 26 ) $barcode_width = 80;
		if( $card_length >= 27 && $card_length <= 30 ) $barcode_width = 85;
		if( $card_length > 30 ) $barcode_width = 90;
		
		$border = false;
		$pdf->Image('@'.$imgdata, $barcode_x_axis, $barcode_y_axis, $barcode_width, '6', 'PNG', '', '', $border, 300, '', false, false, 1, false, false, false);
		// End
		
		// Signature 
		$signature_path = ( $signature != "" ) ? $imagepath . '/' . $signature : "";
		$sign_url = get_url_prefix()."/intranet-tmpl/prog/img/vvsimages/blank.png";
		//$sign_url = "http://127.0.0.1/test/tcpdf/images/signature-2.jpeg";
		if( file_exists( $signature_path ) ) {
			$sign_url = get_url_prefix()."/intranet-tmpl/prog/img/vvsimages/".$signature;
		}
		$sign_x_asis += ( $i > 1 ) ? CARD_WIDTH : 10;
		$sign_y_asis = 44 + (CARD_HEIGHT*$tr);
		$pdf->Image($sign_url, $sign_x_asis, $sign_y_asis, 21, 6, '', '', '', true, 300, '', false, false, 0, false, false, false);
		// End
		
		$last = ( $i == 5 ) ? 'style="border-right:solid 1px #000;"' : "";
		$table .= '<td '.$last.'><br /><br /><br /><br />
			<table style="width:100%;">
			<tr style="line-height:95%;">
				<td class="left"><b> USER NAME </b>&nbsp;</td>
				<td class="right">: '.$member_name.'</td>
			</tr>
			<tr>
				<td class="left"><b> USER ID </b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
				<td class="right">: '.$cardnumber.'</td>
			</tr>
			<tr>
				<td class="left"><b> VALID UPTO </b>&nbsp;</td>
				<td class="right">: '.$dateexpiry.'</td>
			</tr>
			<tr><td colspan="2"></td></tr>
			</table>
		</td>';
	}
	$table .= "</tr>";
}
$table .= "</table>";

$html = <<<EOD
<style>
.main tr td{width:89mm;height:59mm;text-align:center;}
.left{ width:47%;text-align:right;padding-right:15px;}
.right{ width:45%;text-align:left;}
</style>
$table
EOD;

// Print text using writeHTMLCell()
$pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
}
$cardname = "member_cards_".time().".pdf";
// Close and output PDF document
// This method has several options, check the source code documentation for more information.
//$pdf->Output($cardname, 'I');

ob_clean();
//$pdf->Output($cardname, 'D');
$pdf->Output($cardname, 'I');
