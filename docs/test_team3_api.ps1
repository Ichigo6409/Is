param(
    [string]$Secret = "secreto_equipo4_eq3_2026",
    [string]$BaseUrl = "http://127.0.0.1:8000",
    [Parameter(Mandatory=$true)][string]$ProductId
)

function Invoke-Signed {
    param([string]$Method, [string]$Path, [string]$BodyJson)

    $timestamp = [int](([DateTime]::UtcNow - [datetime]'1970-01-01').TotalSeconds)
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    $bodyBytes = $utf8NoBom.GetBytes($BodyJson)

    $sha256 = [System.Security.Cryptography.SHA256]::Create()
    $bodyHash = [BitConverter]::ToString($sha256.ComputeHash($bodyBytes)).Replace("-","").ToLower()
    $payload = "$timestamp|$Method|$Path|$bodyHash"

    $hmac = New-Object System.Security.Cryptography.HMACSHA256
    $hmac.Key = $utf8NoBom.GetBytes($Secret)
    $signature = [BitConverter]::ToString($hmac.ComputeHash($utf8NoBom.GetBytes($payload))).Replace("-","").ToLower()

    $headers = @{ "X-Team-Timestamp" = $timestamp; "X-Team-Signature" = $signature }

    Write-Host "`n=== $Method $Path ===" -ForegroundColor Cyan
    Write-Host "Body: $BodyJson" -ForegroundColor DarkGray

    try {
        $response = Invoke-RestMethod -Uri "$BaseUrl$Path" -Method $Method -Headers $headers -Body $bodyBytes -ContentType "application/json; charset=utf-8"
        Write-Host "OK" -ForegroundColor Green
        $response | ConvertTo-Json -Depth 10
        return $response
    } catch {
        Write-Host "ERROR:" -ForegroundColor Red
        Write-Host $_.Exception.Message
        if ($_.Exception.Response) {
            $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
            Write-Host $reader.ReadToEnd() -ForegroundColor Red
        }
        return $null
    }
}

$orderId = "ORD-T3-$(Get-Date -UFormat %s)"

Write-Host "`n########## TEST 1: availability ##########" -ForegroundColor Magenta
Invoke-Signed -Method "POST" -Path "/api/equipo4/integration/availability" -BodyJson "{`"items`":[{`"product_id`":`"$ProductId`",`"variant_id`":null,`"quantity`":1}]}"

Write-Host "`n########## TEST 2: reservations ##########" -ForegroundColor Magenta
$resKey = "res-$(Get-Date -UFormat %s)"
$res = Invoke-Signed -Method "POST" -Path "/api/equipo4/integration/reservations" -BodyJson "{`"order_id`":`"$orderId`",`"idempotency_key`":`"$resKey`",`"source`":`"CHECKOUT`",`"items`":[{`"product_id`":`"$ProductId`",`"variant_id`":null,`"quantity`":1}]}"

if ($res -and $res.reservation_id) {
    $rid = $res.reservation_id
    Write-Host "`n>>> reservation_id = $rid" -ForegroundColor Yellow

    Write-Host "`n########## TEST 3: confirm ##########" -ForegroundColor Magenta
    $confKey = "conf-$(Get-Date -UFormat %s)"
    Invoke-Signed -Method "POST" -Path "/api/equipo4/integration/reservations/$rid/confirm" -BodyJson "{`"order_id`":`"$orderId`",`"idempotency_key`":`"$confKey`"}"

    Write-Host "`n########## TEST 4: release ##########" -ForegroundColor Magenta
    $resKey2 = "res2-$(Get-Date -UFormat %s)"
    $res2 = Invoke-Signed -Method "POST" -Path "/api/equipo4/integration/reservations" -BodyJson "{`"order_id`":`"$orderId-2`",`"idempotency_key`":`"$resKey2`",`"source`":`"CHECKOUT`",`"items`":[{`"product_id`":`"$ProductId`",`"variant_id`":null,`"quantity`":1}]}"

    if ($res2 -and $res2.reservation_id) {
        $relKey = "rel-$(Get-Date -UFormat %s)"
        Invoke-Signed -Method "POST" -Path "/api/equipo4/integration/reservations/$($res2.reservation_id)/release" -BodyJson "{`"order_id`":`"$orderId-2`",`"reason`":`"TEST`",`"idempotency_key`":`"$relKey`"}"
    }
}

Write-Host "`n=== FIN ===" -ForegroundColor Green