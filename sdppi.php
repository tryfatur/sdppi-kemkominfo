<?php
	header("Content-Type: text/html; charset=ISO-8859-1");

	/*
	* Generate URL untuk scrapping data dari SDPPI Kominfo pertahunnya.
	*/
	function sdppi_url($year)
	{
		for ($i=1; $i < 13; $i++)
		{ 
			if ($i < 10)
			{
				$url     = 'http://sdppi.kominfo.go.id/downloads/43/RHU-'.$year.'0'.$i.'.htm';
				$headers = get_headers($url);
				$status  = substr($headers[0], 9, 3);

				//Memastikan link yang dihasilkan dapat diakses
				if ($status == '200')
					$result[$i-1] = $url;
			}
			else
			{
				$url     = 'http://sdppi.kominfo.go.id/downloads/43/RHU-'.$year.$i.'.htm';
				$headers = get_headers($url);
				$status  = substr($headers[0], 9, 3);

				if ($status == '200')
					$result[$i-1] = $url;
			}
		}

		return array_values($result);
	}

	/*
	* Core function scrapping data (full docs: https://gist.github.com/anchetaWern/6150297)
	* $url: String, URL yang ingin di scrap.
	* $xpath_query: String, XPATH Query.
	*/
	function scrap_me($url, $xpath_query = '//div/table/tr/td')
	{
		$html        = file_get_contents($url);
		$scrapme_doc = new DOMDocument();
		$result      = [];

		libxml_use_internal_errors(TRUE);

		if(!empty($html))
		{
			$scrapme_doc->loadHTML($html);
			libxml_clear_errors();

			$scrapme_xpath = new DOMXPath($scrapme_doc);
			$scrapme_row = $scrapme_xpath->query($xpath_query);

			if($scrapme_row->length > 0)
			{
				foreach($scrapme_row as $row)
					$result[] = preg_replace('/\s+/', ' ', utf8_decode($row->nodeValue));
			}
		}

		return $result;
	}

	/*
	* Custom function untuk menyesuaikan format data hasil scrap ke bentuk yang lebih mudah di olah.
	* $scrap_raw: Array of string, Data scrap mentah hasil dari function scrap_me().
	* $slice_array: Int, jumlah elemen array yang ingin di buang, dalam hal ini header data.
	* $field_count: Int, jumlah kolom array yang ada atau ingin dibuat.
	*/
	function custom_sdppi($scrap_raw, $slice_array = 44, $field_count = 10)
	{
		$data = [];

		if (!is_null($scrap_raw))
		{
			$result      = array_slice($scrap_raw, $slice_array); // Menghilangkan data header/array header
			$total_array = count($result);
			$j           = 0;

			for ($i=0; $i < $total_array; $i++)
			{
				$temp = array_slice($result, $j, $field_count);
				// Memisahkan data per field_count elemen dengan syarat array tidak kosong, 
				// data pertama setiap array adalah integer dan tidak lebih dari jumlah field_count - 1
				if (!empty($temp) AND is_numeric($temp[0]) AND count($temp) > ($field_count - 1))
				{
					$data[$i] = $temp;
					$j += $field_count;
				}
			}
		}

		return $data;
	}

	/*
	* Function untuk convert array ke CSV dan Force download.
	* $header: Array, list header array.
	* $data: Array, data yang sudah dalam bentuk array 2 dimensi, hasil dari function custom_sdppi()
	* $filename: String, nama file CSV yang nanti di generate.
	*/
	function generate_csv($header, $data, $filename)
	{
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename = '.$filename.'.csv');

		$fp = fopen('php://output', 'w');

		fputcsv($fp, $header);
		
		foreach($data as $row)
			fputcsv($fp, $row);

		fclose($fp);
	}

	$list_url = sdppi_url(2010);
	$header   = array('no','nomor_memo_dinas', 'tgl_memo', 'tgl_upload', 'nomor_permohonan', 'nama_pemohon', 'nama_alat_perangkat', 'merk_model_type', 'negara_pembuat', 'keterangan');
	$counter  = 0;
	
	for ($i=0; $i < count($list_url); $i++)
	{
		$raw = scrap_me($list_url[$i]);
		if (count($raw) == 0)
		{
			// Custom XPATH Query karena beberapa data memiliki format HTML yang berbeda
			$raw = scrap_me($list_url[$i], '//table/tr/td');
			if (count($raw) == 0)
				$raw = scrap_me($list_url[$i], '//table/tbody/tr/td');
		}

		// Bagan perulangan ini mencari index array setiap awal row dan jumlah row
		$id = [];
		foreach ($raw as $key => $value)
		{
			if (is_numeric($value) AND $value < 3)
			{
				$id[] = $key;
				if (count($id) == 2)
					break;
			}
		}

		$data[$i]   = custom_sdppi($raw, $id[0], $id[1] - $id[0]);
		$total_data = count($data[$i]);
		for ($j=0; $j < $total_data; $j++)
		{ 
			$final_result[$counter] = $data[$i][$j];
			$counter++;
		}
	}

	generate_csv($header, $final_result, 'sdppi_2010');
?>