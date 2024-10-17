<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include(__DIR__."/fldb.php");
$schema = FLDB_open(__DIR__."/data/users");

if($_SERVER["REQUEST_METHOD"] === "POST") {
	if(@$_POST["command"] === "FLD_APP") {
		$index = @$_POST["field"] !== "" ? intval($_POST["field"]) : null;
		$field = (object)[
			"index" => $index,
			"name" => $_POST["name"],
			"encoding" => $_POST["encoding"],
			"length" => intval($_POST["length"])
		];
		if($index !== null)
			$schema->fields[$index] = $field;
		else
			$schema->fields[] = $field;
		FLDB_define($schema);
	}
	else if(@$_POST["command"] === "FLD_DEL") {
		$index = intval(@$_POST["field"]);
		array_splice($schema->fields, $index, 1);
		FLDB_define($schema);
	}
	else if(@$_POST["command"] === "FLD_LFT") {
		$index = intval(@$_POST["field"]);
		if($index <= 0) exit;
		$temp = $schema->fields[$index - 1];
        $schema->fields[$index - 1] = $schema->fields[$index];
        $schema->fields[$index] = $temp;
		FLDB_define($schema);
	}
	else if(@$_POST["command"] === "FLD_RGT") {
		$index = intval(@$_POST["field"]);
		if($index >= count($schema->fields) - 1) exit;
		$temp = $schema->fields[$index + 1];
        $schema->fields[$index + 1] = $schema->fields[$index];
        $schema->fields[$index] = $temp;
		FLDB_define($schema);
	}
	else if(@$_POST["command"] === "ITM_APP") {
		$index = $_POST["index"] !== "" ? intval($_POST["index"]) : null;
		$item = (object)[];
		foreach($schema->fields as $i => $field)
			if(array_key_exists($i, $_POST)) {
				if($field->encoding === "BIT") {
					$bytes = 0;
					$count = strlen($_POST[$i]);
					for($t = 0; $t < $count; $t++)
						$bytes = FLDB_bitset($bytes, $count - 1 - $t, $_POST[$i][$t] === "1");
					$item->{$field->name} = $bytes;
					continue;
				}
				
				$item->{$field->name} = $_POST[$i];
			}
		FLDB_set($schema, $index, $item);
	}
	else if(@$_POST["command"] === "ITM_DEL") {
		$index = $_POST["index"] !== "" ? intval($_POST["index"]) : null;
		FLDB_delete($schema, $index);
	}
	
	exit;
}
?>
<html>
	<head>
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
		<style>
			* {
				outline:none;
				font-family: "Share Tech Mono";
				border: none;
				padding: 0;
				margin: 0;
				font-weight: normal;
				border-collapse: collapse;
			}
			html {
				--colour: #FFFFFF;
				--border-colour: #999999;
				--unit: 1.75rem;
			}
			body {
				background: #000000;
				color: var(--colour);
			}
			table { width:100%; }
			td, th { padding:2px; }
			input,select,button {
				width: 100%;
				font-size: 1rem;
				background: Transparent;
				color: var(--colour);
				height: var(--unit);
				cursor: pointer;		
			}
			input[type=checkbox] {
				width: var(--unit);
				min-width: var(--unit);
				max-width: var(--unit);
				vertical-align: middle;
				appearance: none;
				cursor: pointer;
			}
				input[type=checkbox]:checked:after {
					display: block;
					content: "";
					margin-left: calc(var(--unit) * 0.2);
					margin-top: calc(var(--unit) * 0.2);
					background: var(--colour);
					width: 1rem;
					height: 1rem;
				}
			input { cursor:text }
			button { cursor:pointer; }
			
			.layout { display:flex; }
			.layout>div { flex-grow:1; }
			
			.square {
				width: var(--unit);
				min-width: var(--unit);
				max-width: var(--unit);
			}
			
			.control>* { border:solid 1px var(--border-colour); }
			.control-column>*>*>* { border-top:solid 1px var(--border-colour); }
			.control-column>*:last-child>*>* { border-bottom:solid 1px var(--border-colour); }
			.control-row>*>* { border-left:solid 1px var(--border-colour); }
			.control-row>*:last-child>* { border-right:solid 1px var(--border-colour); }
		</style>
		<script>
			function bitset(byte, index, value) {
				if(value) return byte | (1 << index);
				else return byte & ~(1 << index);
			}
			function bitget(byte, index) {
				return (byte & (1 << index)) !== 0;
			}
			
			function field_add(node) {
				fetch("", {
					method: "POST",
					headers: { "Content-Type":"application/x-www-form-urlencoded" },
					body: new URLSearchParams({
						command: "FLD_APP",
						field: "",
						name: node.querySelector("[name=name]").value,
						encoding: node.querySelector("[name=encoding]").value,
						length: parseInt(node.querySelector("[name=length]").value)
					}).toString()
				}).then(() => { window.location.reload() })
			}
			function field_save(node) {
				fetch("", {
					method: "POST",
					headers: { "Content-Type":"application/x-www-form-urlencoded" },
					body: new URLSearchParams({
						command: "FLD_APP",
						field: node.getAttribute("index"),
						name: node.querySelector("[name=name]").value,
						encoding: node.querySelector("[name=encoding]").value,
						length: parseInt(node.querySelector("[name=length]").value)
					}).toString()
				}).then(() => { window.location.reload() })
			}
			function field_remove(node) {
				fetch("", {
					method: "POST",
					headers: { "Content-Type":"application/x-www-form-urlencoded" },
					body: new URLSearchParams({
						command: "FLD_DEL",
						field: node.getAttribute("index")
					}).toString()
				}).then(() => { window.location.reload() })
			}
			function field_left(node) {
				fetch("", {
					method: "POST",
					headers: { "Content-Type":"application/x-www-form-urlencoded" },
					body: new URLSearchParams({
						command: "FLD_LFT",
						field: node.getAttribute("index")
					}).toString()
				}).then(() => { window.location.reload() })
			}
			function field_right(node) {
				fetch("", {
					method: "POST",
					headers: { "Content-Type":"application/x-www-form-urlencoded" },
					body: new URLSearchParams({
						command: "FLD_RGT",
						field: node.getAttribute("index")
					}).toString()
				}).then(() => { window.location.reload() })
			}
			function item_apply(node) {
				let item = {}
				for(let td of node.querySelectorAll("td"))
					if(td.hasAttribute("encoding")) {
						let inputs = td.querySelectorAll("input")
						if(td.getAttribute("encoding") === "BIT") {
							let bytes = "";
							for(let i = 0; i < inputs.length; i++)
								bytes += inputs[i].checked ? "1" : "0";
							item[td.getAttribute("index")] = bytes;
						}
						else
							item[td.getAttribute("index")] = inputs[0].value
					}
				fetch("", {
					method: "POST",
					headers: { "Content-Type":"application/x-www-form-urlencoded" },
					body: new URLSearchParams(Object.assign({
						command: "ITM_APP",
						index: node.hasAttribute("index") ? node.getAttribute("index") : ""
					}, item)).toString()
				}).then(() => { window.location.reload() })
			}
			function item_delete(node) {
				fetch("", {
					method: "POST",
					headers: { "Content-Type":"application/x-www-form-urlencoded" },
					body: new URLSearchParams({
						command: "ITM_DEL",
						index: node.hasAttribute("index") ? node.getAttribute("index") : ""
					}).toString()
				}).then(() => { window.location.reload() })
			}
		</script>
	</head>
	<body style="padding:2px;">
		<table>
			<thead>
				<th></th>
				<?
					function echo_field($index, $field) {
						$encodings = (object)[
							"BIT" => "BIT",
							"UTF8" => "UTF-8",
							"SCHR" => "c",
							"UCHR" => "C"
						];
						?>
						<th class="control-column" index="<? echo(htmlentities($index)); ?>">
							<div class="control-row layout">
								<div>
									<input type="text" name="name" value="<? echo(htmlentities($field->name)); ?>"/>
								</div>
								<? if($index !== null) { ?>
									<div class="square">
										<button onclick="field_save(this.closest('th'))">🖬</button>
									</div>
									<div class="square">
										<button onclick="field_remove(this.closest('th'))">&times;</button>
									</div>
								<? } else { ?>
									<div class="square">
										<button onclick="field_add(this.closest('th'))">&plus;</button>
									</div>
								<? } ?>
							</div>
							<div class="control-row layout">
								<div style="width:50%;">
									<select name="encoding">
										<?
											foreach($encodings as $label => $value)
												echo("<option value='".htmlentities($value)."' ".($field->encoding == $value ? " selected" : "").">".htmlentities($label)."</option>");
										?>
									</select>
								</div>
								<div style="width:50%">
									<input type="number" step="1" name="length" value="<? echo(htmlentities($field->length)); ?>"/>
								</div>
								<? if($index !== null) { ?>
									<div class="square"><button onclick="field_left(this.closest('th'))">&lt;</button></div>
									<div class="square"><button onclick="field_right(this.closest('th'))">&gt;</button></div>
								<? } ?>
							</div>
						</th>
						<?
					}
					foreach($schema->fields as $i => $field)
						echo_field($i, $field);
					echo_field(null, (object)[
						"name" => "",
						"encoding" => "",
						"length" => 12
					], true);
				?>
			</thead>
			<tbody>
				<?
					function echo_value($index, $field, $value) {
						?>
						<td class="control" index="<? echo(htmlentities($index)); ?>" encoding="<? echo(htmlentities($field->encoding)); ?>" length="<? echo(htmlentities($field->length)); ?>">
							<? if($field->encoding === "BIT") { ?>
								<!--
								<? for($i = 0; $i < $field->length; $i++) { ?>
									<? for($t = 0; $t < 8; $t++) { ?>
										--><input type="checkbox"<? echo($value !== null && FLDB_bitget($value, ($i * 8) + (7 - $t)) ? " checked" : ""); ?>/><!--
									<? } ?>
								<? } ?>
								-->
							<? } else if(strlen($field->encoding) === 1) { ?>
								<input type='number' value='<? echo($value !== null ? htmlentities($value) : ""); ?>'/>
							<? } else { ?>
								<input type='text' value='<? echo($value !== null ? htmlentities($value) : ""); ?>'/>
							<? } ?>
						</td>
						<?
					}
				
					$items = FLDB_browse($schema);
					foreach($items as $index => $item) {
						?>
						<tr index="<? echo(htmlentities($index)); ?>">
							<th class="square"><? echo(htmlentities($index)); ?></th>
							<?
								foreach($schema->fields as $i => $field)
									echo_value($i, $field, $item->{$field->name});
							?>
							<td class="control square" style="display:inline-block"><button onclick="item_apply(this.closest('tr'))">🖬</button></td>
							<td class="control square" style="display:inline-block;"><button onclick="item_delete(this.closest('tr'))">&times;</button></td>
						</tr>
						<?
					}
				?>
				<tr>
					<th class="square">*</th>
					<?
						foreach($schema->fields as $i => $field)
							echo_value($i, $field, null);
					?>
					<td class="control square" style="display:block"><button onclick="item_apply(this.closest('tr'))">&plus;</button></td>
				</tr>
			</tbody>
		</table>
		<footer style="position:fixed; inset:auto 0 0 0;">
			<output>HDSZE:<? echo($schema->header_length); ?>b</output>
			/ <output>ITMSZE:<? echo($schema->item_length); ?>b</output>
			/ <output>ITMCNT:<? echo($schema->item_count); ?></output>
			/ <output>SZE:<? echo($schema->header_length + ($schema->item_length * $schema->item_count)); ?>b</output>
		</footer>
	</body>
</html>
<? FLDB_close($schema); ?>