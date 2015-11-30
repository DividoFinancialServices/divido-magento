<?php
if (!empty($_POST)) { 

	$metadata = array();
	if (!empty($_POST['metadata']) && is_array($_POST['metadata'])) {
		foreach($_POST['metadata'] as $metadataRow) {
			if ($metadataRow['name']) {
				$metadata[$metadataRow['name']] = $metadataRow['value'];
			}
		}
	}
	$data = array(
				'application'=>$_POST['application'],
				'reference'=>$_POST['reference'],
				'status'=>$_POST['status'],
				'metadata'=>$metadata
			);

	$url = $_POST['url'];

	$data_string = json_encode($data);
	$ch = curl_init($url);                                                                      
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                       
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(                               
		'Content-Type: application/json',
		'Content-Length: ' . strlen($data_string))
	);              
																													 
	if (curl_exec($ch)) {
		print '<b><strong style="color:green">SUCCESS: Webhook sent</strong><hr />';
	}
	
}

$statuses = array('PROPOSAL','VERIFIED','ACCEPTED','DEPOSIT-PAID','SIGNED','FULFILLED','ACTION-CUSTOMER','ACTION-RETAILER','ACTION-LENDER','AMENDED','INFO-NEEDED','PREDECLINED','CANCELED','DEFERRED','COMPLETED','RESUBMITTED');

?>
<form method="post">
<table>
	<tr>
		<td style="width:200px;">Response URL:</td>
		<td style="width:400px;"><input type="text" name="url" value="<?php print (!empty($_POST['url'])) ? $_POST['url']:"http://"; ?>" size="30" /></td>
	</tr>
	<tr>
		<td>Application ID:</td>
		<td><input type="text" name="application" value="<?php print (!empty($_POST['application'])) ? $_POST['application']:"123"; ?>" size="30" /></td>
	</tr>
	<tr>
		<td>Reference:</td>
		<td><input type="text" name="reference" value="<?php print (!empty($_POST['reference'])) ? $_POST['reference']:"100123"; ?>" size="30" /></td>
	</tr>
	<tr>
		<td>Status:</td>
		<td><select name="status">
			<?php foreach($statuses as $_status) { ?>
				<option value="<?php print $_status; ?>" <?php print (!empty($_POST['status']) && $_POST['status'] == $_status) ? "selected":""; ?>><?php print $_status; ?></option>			
			<?php } ?>
		</select></td>
	</tr>
</table>
<br />
<strong>Metadata</strong><br />
<?php for($i=0;$i<10;$i++) { ?>
	<input type="text" name="metadata[<?php print $i; ?>][name]" value="<?php print (!empty($_POST['metadata'][$i]['name'])) ? $_POST['metadata'][$i]['name']:""; ?>">&nbsp;
	<input type="text" name="metadata[<?php print $i; ?>][value]" value="<?php print (!empty($_POST['metadata'][$i]['value'])) ? $_POST['metadata'][$i]['value']:""; ?>"><br />
<?php } ?>
<br />
<input type="submit" name="send" value="Submit" />

</form>
