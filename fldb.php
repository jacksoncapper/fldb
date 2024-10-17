<?php
function FLDB_encode($field, $data) {
	if($field->encoding === "BIT")
		return pack("C*", $data);
	else if(strlen($field->encoding) === 1)
		return pack($field->encoding."*", $data === null ? 0 : $data);
	else {
		$data = $data === null ? "" : $data;
		$data = substr($data, 0, $field->length);
		$string = str_repeat("\0", $field->length);
		return substr_replace($string, $data, 0, strlen($data));
	}
}
function FLDB_decode($field, $data_packed) {
	if($field->encoding === "BIT")
		return unpack("C*", $data_packed)[1];
	else if(strlen($field->encoding) === 1)
		return unpack($field->encoding."*", $data_packed)[1];
	else
		return rtrim($data_packed, "\0");
}
function FLDB_bitget($bytes, $index) {
	return ($bytes >> $index) & 1;
}
function FLDB_bitset($bytes, $index, $value) {
	if($value)
        return $bytes | (1 << $index);
    else
        return $bytes & ~(1 << $index);
}

function FLDB_open($filename) {
	$schema = (object)[
		"filename" => $filename,
		"file" => fopen($filename, "c+b"),
		"fields" => [],
		"fields_object" => (object)[],
		"header_length" => 0,
		"body_length" => 0,
		"item_length" => 0,
		"item_count" => 0
	];
	
	$field_count = fread($schema->file, 1);
	if(!$field_count) return $schema;
	$field_count = unpack("C*", $field_count)[1];
	$schema->header_length += 1;
	
	$start = 0;
	for($i = 0; $i < $field_count; $i++) {
		fseek($schema->file, $schema->header_length);
		
		$name = rtrim(fread($schema->file, 32), "\0");
		$encoding = rtrim(fread($schema->file, 16), "\0");
		$lengthx = fread($schema->file, 2);
		$length = unpack("S*", $lengthx)[1];
		
		$schema->fields[] = $schema->fields_object->{$name} = (object)[
			"index" => $i,
			"name" => $name,
			"encoding" => $encoding,
			"start" => $start,
			"length" => $length
		];
		
		$start += $length;
		$schema->item_length += $length;
		$schema->header_length += 50;
	}
	
	clearstatcache();
	$schema->body_length = filesize($schema->filename) - $schema->header_length;
	$schema->item_count = floor($schema->item_length ? $schema->body_length / $schema->item_length : 0);
	return $schema;
}
function FLDB_define($schema) {
	FLDB_close($schema);
	
	$schema_buffer = FLDB_open($schema->filename);
	$items_buffer = FLDB_browse($schema_buffer);
	FLDB_close($schema_buffer);
	
	$backup = file_get_contents($schema->filename);
	file_put_contents($schema->filename.".backup.".implode("", array_map(function() { return random_int(0, 9); }, range(1, 8))), $backup);
	
	$header = pack("C*", count($schema->fields));
	foreach($schema->fields as $field) {
		if($field->encoding === "BIT")
			$field->length = 1;
		else if(strlen($field->encoding) === 1)
			$field->length = [ "C"=>1, "S"=>2, "N" => 2, "V" => 2, "L" => 4, "q"=>8, "Q"=>8, "F"=>4, "D"=>8 ][strtoupper($field->encoding)];
		$header .= FLDB_encode((object)[ "encoding"=>"UTF-8", "length"=>32 ], $field->name);
		$header .= FLDB_encode((object)[ "encoding"=>"UTF-8", "length"=>16 ], $field->encoding);
		$header .= FLDB_encode((object)[ "encoding"=>"S", "length"=>2 ], $field->length);
	}
	file_put_contents($schema->filename, $header);
	
	// Format buffer to preserve compatibility
	foreach($items_buffer as $i => $item) {
		$item_array = get_object_vars($item);
		foreach($item_array as $name => $value) {
			
			$field_index = null;
			foreach($schema_buffer->fields as $buffer_field)
				if($buffer_field->name === $name) {
					$field_index = $buffer_field->index;
					break;
				}
			
			$field_name = null;
			foreach($schema->fields as $field)
				if($field->index === $field_index) {
					$field_name = $field->name;
					break;
				}
			
			$item->{$field_name} = $value;
		}
	}
	
	$schema = FLDB_open($schema->filename);
	foreach($items_buffer as $i => $item)
		FLDB_set($schema, $i, $item);
	return $schema;
}
function FLDB_close($schema) {
	fclose($schema->file);
}
function FLDB_get($schema, $index) {
	fseek($schema->file, $schema->header_length + $index * $schema->item_length);
	$string = fread($schema->file, $schema->item_length);
	$item = (object)[];
	foreach($schema->fields as $field) {
		$substring = substr($string, $field->start, $field->length);
		$item->{$field->name} = FLDB_decode($field, $substring);
	}
	return $item;
}
function FLDB_set($schema, $index, $data) {
	$mode = $index === null ? 0 : 1; // 0:Create, 1:Update
	$index = $index === null ? $schema->item_count : $index;
	foreach($schema->fields as $field) {
		if($mode === 1 && !property_exists($data, $field->name)) continue;
		$string = FLDB_encode($field, @$data->{$field->name} ?? null);
		fseek($schema->file, $schema->header_length + $index * $schema->item_length + $field->start);
		fwrite($schema->file, $string);
	}
	return $index;
}
function FLDB_field_get($schema, $index, $field_name) {
	$field = $schema->fields_object->{$field_name};
	fseek($schema->file, $schema->header_length + $index * $schema->item_length + $field->start);
	$string = fread($schema->file, $field->length);
	$value = FLDB_decode($field, $string);
	return $value;
}
function FLDB_field_set($schema, $index, $field_name, $data) {
	$field = $schema->fields_object->{$field_name};
	$string = FLDB_encode($field, $data);
	fseek($schema->file, $schema->header_length + $index * $schema->item_length + $field->start);
	fwrite($schema->file, $string);
}
function FLDB_clear($schema, $index) {
	fseek($schema->file, $schema->header_length + $index * $schema->item_length);
	fwrite($schema->file, str_repeat("\0", $schema->item_length));
}
function FLDB_delete($schema, $index) {
	$position = $schema->header_length + $index * $schema->item_length;
	$position_after = $position + $schema->item_length;
	clearstatcache();
	fseek($schema->file, $position_after);
	$eof = filesize($schema->filename) - $position_after;
	$post_content = $eof ? fread($schema->file, $eof) : "";
    ftruncate($schema->file, $position);
    fseek($schema->file, $position);
    fwrite($schema->file, $post_content);
}
function FLDB_browse($schema, $start = 0, $length = null) {
	$items = [];
	$stop = $length !== null ? min($start + $length, $schema->item_count) : $schema->item_count;
	for($index = $start; $index < $stop; $index++) {
		fseek($schema->file, $schema->header_length + $index * $schema->item_length);
		$string = fread($schema->file, $schema->item_length);
		$item = (object)[];
		foreach($schema->fields as $field)
			if(property_exists($field, "start")) { // Ensure field is compiled.
				$substring = substr($string, $field->start, $field->length);
				$item->{$field->name} = FLDB_decode($field, $substring);
			}
		$items[] = $item;
	}
	return $items;
}
function FLDB_filter($schema, $field_name, $callback) {
	$field = $schema->fields_object->{$field_name};
	$items = [];
	for($i = 0; $i < $schema->item_count; $i++) {
		fseek($schema->file, $schema->header_length + $i * $schema->item_length + $field->start);
		$string = fread($schema->file, $field->length);
		$value = FLDB_decode($field, $string);
		if($callback($value))
			$items[] = $i;
	}
	return $items;
}
?>