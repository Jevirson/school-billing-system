$outputPath = "C:\Users\Virgi\OneDrive\Desktop\ITproj\school-billing-system\SchoolBillingSystem_SRS.pdf"
$textOutputPath = "C:\Users\Virgi\OneDrive\Desktop\ITproj\school-billing-system\SchoolBillingSystem_SRS.txt"

function Escape-PdfText {
    param([string]$Text)
    $escaped = $Text.Replace('\\', '\\')
    $escaped = $escaped.Replace('(', '\(')
    $escaped = $escaped.Replace(')', '\)')
    return $escaped
}

function New-PageStream {
    param(
        [string[]]$Lines,
        [string]$Title,
        [string]$Subtitle = ""
    )

    $content = New-Object System.Collections.Generic.List[string]
    $y = 760

    if ($Title) {
        $content.Add("BT")
        $content.Add("/F2 20 Tf")
        $content.Add("50 $y Td")
        $content.Add("(" + (Escape-PdfText $Title) + ") Tj")
        $content.Add("ET")
        $y -= 36
    }

    if ($Subtitle) {
        $content.Add("BT")
        $content.Add("/F1 12 Tf")
        $content.Add("50 $y Td")
        $content.Add("(" + (Escape-PdfText $Subtitle) + ") Tj")
        $content.Add("ET")
        $y -= 28
    }

    foreach ($line in $Lines) {
        if ($line -match '^\s*$') {
            $y -= 12
            continue
        }

        $font = "/F1 11 Tf"
        $indent = 54
        if ($line.StartsWith('CHAPTER ') -or $line.StartsWith('SECTION ') -or $line.StartsWith('1.') -or $line.StartsWith('2.') -or $line.StartsWith('3.') -or $line.StartsWith('4.') -or $line.StartsWith('5.') -or $line.StartsWith('6.') -or $line.StartsWith('7.') -or $line.StartsWith('8.') -or $line.StartsWith('9.') -or $line.StartsWith('10.') -or $line.StartsWith('11.') -or $line.StartsWith('12.') -or $line.StartsWith('13.') -or $line.StartsWith('14.')) {
            $font = "/F2 12 Tf"
            $indent = 52
        }

        $content.Add("BT")
        $content.Add($font)
        $content.Add("$indent $y Td")
        $content.Add("(" + (Escape-PdfText $line) + ") Tj")
        $content.Add("ET")
        $y -= 18
    }

    return ($content -join "`n")
}

function New-PdfFile {
    param(
        [string]$OutputPath,
        [string[]]$PageLines,
        [string]$Title,
        [string]$Subtitle = ""
    )

    $pages = @()
    $pageText = [System.Collections.Generic.List[string]]::new()
    $pageCounter = 1

    foreach ($line in $PageLines) {
        $pageText.Add($line)
        if ($pageText.Count -ge 32) {
            $pages += New-PageStream -Title $Title -Subtitle $Subtitle -Lines $pageText.ToArray()
            $pageText.Clear()
            $Title = ""
            $Subtitle = ""
        }
    }

    if ($pageText.Count -gt 0) {
        $pages += New-PageStream -Title $Title -Subtitle $Subtitle -Lines $pageText.ToArray()
    }

    $objects = New-Object System.Collections.Generic.List[string]
    $objects.Add("<< /Type /Catalog /Pages 2 0 R >>")
    $objects.Add("<< /Type /Pages /Kids [")

    $pageCount = $pages.Count
    $pageObjNumbers = @()
    for ($i = 0; $i -lt $pageCount; $i++) {
        $pageObjNumbers += (3 + ($i * 2))
    }

    for ($i = 0; $i -lt $pageCount; $i++) {
        $pageObjNumbers[$i] = (3 + ($i * 2))
    }

    $kids = ($pageObjNumbers | ForEach-Object { "$_ 0 R" }) -join " "
    $objects[1] = "<< /Type /Pages /Kids [$kids] /Count $pageCount >>"

    for ($i = 0; $i -lt $pageCount; $i++) {
        $contentObj = 4 + ($i * 2)
        $pageObj = 3 + ($i * 2)
        $objects.Add("<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents $contentObj 0 R >>")
        $objects.Add("<< /Length $($pages[$i].Length) >>`nstream`n$($pages[$i])`nendstream")
    }

    $objects.Add("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>")
    $objects.Add("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>")

    $pdf = New-Object System.Text.StringBuilder
    $pdf.Append("%PDF-1.4`n")

    $offsets = New-Object System.Collections.Generic.List[int]
    $offsets.Add(0)

    for ($i = 0; $i -lt $objects.Count; $i++) {
        $offsets.Add($pdf.Length)
        $pdf.Append(($i + 1).ToString() + " 0 obj`n")
        $pdf.Append($objects[$i])
        $pdf.Append("`nendobj`n")
    }

    $xrefPosition = $pdf.Length
    $pdf.Append("xref`n")
    $pdf.Append("0 " + ($objects.Count + 1).ToString() + "`n")
    $pdf.Append("0000000000 65535 f `n")
    for ($i = 0; $i -lt $objects.Count; $i++) {
        $pdf.Append($offsets[$i + 1].ToString("D10") + " 00000 n `n")
    }

    $pdf.Append("trailer`n")
    $pdf.Append("<< /Size " + ($objects.Count + 1).ToString() + " /Root 1 0 R >>`n")
    $pdf.Append("startxref`n")
    $pdf.Append($xrefPosition.ToString() + "`n")
    $pdf.Append("%%EOF")

    [System.IO.File]::WriteAllText($OutputPath, $pdf.ToString(), [System.Text.Encoding]::ASCII)
}

$lines = @(
    "SCHOOL BILLING SYSTEM",
    "SOFTWARE REQUIREMENTS SPECIFICATION",
    "",
    "Prepared by:",
    "Jevirson Andrew H. Intes",
    "",
    "Abstract",
    "This project proposes the development of a School Billing System designed to streamline and automate fee management, payment collection, and financial monitoring for educational institutions. The system will provide a centralized platform for handling student accounts, invoices, payment history, and outstanding balances while improving reliability, accuracy, and administrative efficiency.",
    "",
    "1. INTRODUCTION",
    "The School Billing System is an automated solution for managing student fees and school financial transactions. It is intended for administrators, staff, and students to monitor tuition, generate invoices, record payments, and maintain accurate financial records in an organized and secure way.",
    "The system reduces dependence on manual recordkeeping and improves transparency for all parties involved. It is designed to manage school fees, terms, payment schedules, and balance updates in a consistent and reliable manner.",
    "",
    "2. BACKGROUND OF THE STUDY",
    "Manual billing systems are often prone to delays, duplication of records, calculation errors, and difficulty in tracking student balances. Many schools still rely on spreadsheets and paper records, making it harder to generate reports and maintain accurate financial data.",
    "The School Billing System addresses these concerns by providing an efficient digital process for student billing, fee management, payment tracking, and financial reporting. This modern approach supports better service delivery and smoother school operations.",
    "",
    "3. PROBLEM STATEMENT",
    "The school currently experiences difficulties in managing billing records manually. These issues include inaccurate student balances, missed payment updates, delayed invoice generation, duplicate records, limited reporting, and poor financial visibility.",
    "Without a centralized billing system, school administrators spend too much time on repetitive financial tasks and are at a higher risk of errors. There is a need for a system that will automate the process while maintaining data security and financial integrity.",
    "",
    "4. OBJECTIVES OF THE STUDY",
    "To automate school fee collection and payment recording.",
    "To generate accurate billing statements and invoices for each student.",
    "To monitor outstanding balances and payment statuses.",
    "To reduce manual workload and human error in school financial operations.",
    "To provide role-based access for administrators and students.",
    "To improve financial transparency and reporting for decision-making.",
    "",
    "5. SCOPE OF THE STUDY",
    "The system includes student information management, school fee definitions, invoice generation, payment recording, account statements, and financial reporting. It focuses on the billing and payment process of the school and is intended to support administrative and student financial needs.",
    "The system is limited to fees, student accounts, and payment operations. It does not cover academic grading or general school administration beyond billing-related functions.",
    "",
    "6. FUNCTIONAL REQUIREMENTS",
    "The system shall allow administrators to manage student records and billing information.",
    "The system shall allow administrators to add fees, school terms, and billing policies.",
    "The system shall generate student bills and itemized invoices with details of charges and payment status.",
    "The system shall record student payments and automatically update balances.",
    "The system shall prevent duplicate billing entries and invalid overpayments.",
    "The system shall provide statement of account reports and payment history for students.",
    "The system shall support secure login and role-based access control.",
    "The system shall allow authorized users to generate financial reports and summaries.",
    "",
    "7. NON-FUNCTIONAL REQUIREMENTS",
    "The system must be reliable, accurate, and easy to use.",
    "The system must protect financial data through secure authentication and access control.",
    "The system must be responsive and compatible with standard web browsers.",
    "The system must be scalable to support future school growth and additional features.",
    "The system must maintain data integrity and minimize errors in transaction processing.",
    "",
    "8. USERS OF THE SYSTEM",
    "Administrator: manages student accounts, billing setup, invoices, and reports.",
    "Student: views billing statements, payment records, and outstanding balances.",
    "School staff or authorized personnel: access billing data based on assigned privileges.",
    "",
    "9. BENEFITS OF THE SYSTEM",
    "Improved efficiency in school fee management.",
    "Reduced manual workload and paperwork.",
    "Accurate and transparent student billing records.",
    "Better payment tracking and monitoring of balances.",
    "Faster generation of invoices and reports.",
    "Enhanced confidence in financial decision-making.",
    "",
    "10. CONCLUSION",
    "The School Billing System is a necessary solution for any educational institution that wants to improve its financial management efficiency. By automating the process of fee collection, invoice generation, and payment tracking, the system provides a more accurate, transparent, and secure billing environment. This will support better financial operations and improve the overall school experience for administrators and students.",
    "",
    "Prepared by:",
    "Jevirson Andrew H. Intes"
)

$finalText = $lines -join "`r`n"
[System.IO.File]::WriteAllText($textOutputPath, $finalText, [System.Text.Encoding]::UTF8)

New-PdfFile -OutputPath $outputPath -PageLines $lines -Title "SCHOOL BILLING SYSTEM" -Subtitle "Software Requirements Specification`nPrepared by: Jevirson Andrew H. Intes"

Write-Host "PDF created successfully: $outputPath"
Write-Host "Text file created successfully: $textOutputPath"
