$newsDir = "c:\Users\Arnov\OneDrive - BRG Schoren\RVHard\RV Hard Webseite\news"
$updated = 0
$skipped = 0

Get-ChildItem $newsDir -Filter "*.html" | ForEach-Object {
    $file = $_
    $path = $file.FullName
    $content = Get-Content $path -Raw -Encoding UTF8
    $original = $content
    
    # Add unpkg preconnect if missing
    if ($content -match "unpkg\.com/aos" -and $content -notmatch 'preconnect.*unpkg\.com') {
        $content = $content -replace '(<link [^>]*href="https://unpkg\.com/aos)', '<link rel="preconnect" href="https://unpkg.com">' + "`n    " + '$1'
    }
    
    # Add cdnjs preconnect if missing  
    if ($content -match "cdnjs\.cloudflare\.com" -and $content -notmatch 'preconnect.*cdnjs\.cloudflare\.com') {
        $content = $content -replace '(<link [^>]*href="https://cdnjs\.cloudflare\.com)', '<link rel="preconnect" href="https://cdnjs.cloudflare.com">' + "`n    " + '$1'
    }
    
    if ($content -ne $original) {
        Set-Content $path $content -Encoding UTF8
        $updated++
    } else {
        $skipped++
    }
}

Write-Host "Updated: $updated files"
Write-Host "Skipped: $skipped files"
