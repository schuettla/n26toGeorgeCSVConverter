<?php
// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvFile']) && $_FILES['csvFile']['error'] === UPLOAD_ERR_OK) {
    // Get uploaded file
    $inputFile = $_FILES['csvFile']['tmp_name'];
    
    // Generate output filename with current date
    $currentDate = date('Y-m-d');
    $outputFilename = 'converted_output_' . $currentDate . '.csv';
    
    // Read input file
    $inputData = file_get_contents($inputFile);
    
    // Detect file encoding and convert to UTF-8 if needed
    $encoding = mb_detect_encoding($inputData, ['UTF-8', 'ISO-8859-1', 'ISO-8859-15'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $inputData = mb_convert_encoding($inputData, 'UTF-8', $encoding);
    }
    
    // Parse CSV
    $lines = explode("\n", $inputData);
    $header = str_getcsv(array_shift($lines), ';');
    
    // Prepare output data array (without header yet)
    $outputData = [];
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $row = str_getcsv($line, ';');
        if (count($row) < count($header)) continue;
        
        $rowData = array_combine($header, $row);
        
        // Format amount (replace dot with comma for European format)
        $amount = $rowData['Amount (EUR)'] ?? '';
        $amount = str_replace('.', ',', $amount);
        
        // Create new output row
        $newRow = [
            $rowData['Booking Date'] ?? '',            // Buchungsdatum
            $rowData['Partner Name'] ?? '',            // Partnername
            $rowData['Partner Iban'] ?? '',            // Partner IBAN
            '',                                        // BIC/SWIFT
            '',                                        // Partner Kontonummer
            '',                                        // Bankleitzahl
            $amount,                                   // Betrag
            'EUR',                                     // Währung
            '',                                        // Buchungs-Info
            $rowData['Payment Reference'] ?? ''        // Zahlungsreferenz
        ];
        
        $outputData[] = $newRow;
    }
    
    // Sort the data by Buchungsdatum
    usort($outputData, function($a, $b) {
        $dateA = DateTime::createFromFormat('d.m.Y', $a[0]);
        $dateB = DateTime::createFromFormat('d.m.Y', $b[0]);
        
        if (!$dateA || !$dateB) return 0;
        
        if ($dateA == $dateB) {
            return 0;
        }
        return ($dateA < $dateB) ? -1 : 1;
    });
    
    // Add header as the first row
    array_unshift($outputData, [
        'Buchungsdatum', 
        'Partnername', 
        'Partner IBAN', 
        'BIC/SWIFT', 
        'Partner Kontonummer', 
        'Bankleitzahl', 
        'Betrag', 
        'Währung', 
        'Buchungs-Info', 
        'Zahlungsreferenz'
    ]);
    
    // Create temp file for output
    $outputFile = tempnam(sys_get_temp_dir(), 'csv');
    $fp = fopen($outputFile, 'w');
    foreach ($outputData as $row) {
        fputcsv($fp, $row, ';');
    }
    fclose($fp);
    
    // Send file to browser for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $outputFilename . '"');
    header('Content-Length: ' . filesize($outputFile));
    readfile($outputFile);
    
    // Clean up
    unlink($outputFile);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank CSV Converter</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #333;
            margin-top: 0;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .instructions {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
            border-left: 4px solid #4CAF50;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bank CSV Converter</h1>
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="csvFile">Select N26 CSV File:</label>
                <input type="file" id="csvFile" name="csvFile" accept=".csv" required>
            </div>
            <button type="submit" class="btn">Convert and Download</button>
        </form>
        
        <div class="instructions">
            <h3>Instructions</h3>
            <p>This tool converts N26 bank statement CSV files to George format.</p>
            <ol>
                <li>Select your N26 CSV file using the button above</li>
                <li>Click "Convert and Download" to process the file</li>
                <li>The converted file will download automatically</li>
            </ol>
            <p><strong>Note:</strong> The converted file will be sorted by date and include the current date in the filename.</p>
        </div>
    </div>
</body>
</html>
