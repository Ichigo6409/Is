param(
    [string]$Secret = "secreto_equipo4_eq3_2026",
    [string]$BaseUrl = "http://127.0.0.1:8004",
    [Parameter(Mandatory=$true)][string]$ProductId,
    [Parameter(Mandatory=$true)][string]$LocationId
)
$U8 = New-Object System.Text.UTF8Encoding($false)
function S($m,$p,$b="") {
  $t = [int](([DateTime]::UtcNow - [datetime]'1970-01-01').TotalSeconds)
  $by = $U8.GetBytes($b)
  $h = [BitConverter]::ToString([System.Security.Cryptography.SHA256]::Create().ComputeHash($by)).Replace("-","").ToLower()
  $pl = "$t|$m|$p|$h"
  $hm = New-Object System.Security.Cryptography.HMACSHA256
  $hm.Key = $U8.GetBytes($Secret)
  $sg = [BitConverter]::ToString($hm.ComputeHash($U8.GetBytes($pl))).Replace("-","").ToLower()
  $hd = @{ "X-Team-Timestamp"=$t; "X-Team-Signature"=$sg; "Accept"="application/json" }
  Write-Host "`n=== $m $p ===" -ForegroundColor Cyan
  try {
    $a = @{Uri="$BaseUrl$p";Method=$m;Headers=$hd;ContentType="application/json; charset=utf-8"}
    if($b){$a.Body=$by}
    $r = Invoke-RestMethod @a
    Write-Host "OK" -ForegroundColor Green
    $r | ConvertTo-Json -Depth 10
    return $r
  } catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
    if($_.Exception.Response){
      Write-Host (New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())).ReadToEnd() -ForegroundColor Red
    }
  }
}
$ts = [int](([DateTime]::UtcNow - [datetime]'1970-01-01').TotalSeconds)

Write-Host "`n### TEST 1: RETURN (devolucion cliente) ###" -ForegroundColor Magenta
S POST "/api/equipo4/integration/returns" (@{
  order_id="TEST-RET-1"; idempotency_key="ret-$ts"; reason="TEST"
  items=@(@{ product_id=$ProductId; variant_id=$null; location_id=$LocationId; quantity=1 })
} | ConvertTo-Json -Depth 5 -Compress) | Out-Null

Write-Host "`n### TEST 2: ADJUSTMENT (ajuste/merma) ###" -ForegroundColor Magenta
S POST "/api/equipo4/integration/adjustments" (@{
  product_id=$ProductId; location_id=$LocationId; delta=-1; reason="TEST_MERMA"
  idempotency_key="adj-$ts"; actor_id="TEST"
} | ConvertTo-Json -Compress) | Out-Null

Write-Host "`n### TEST 3: TRANSFER (misma ubicacion debe dar 422) ###" -ForegroundColor Magenta
S POST "/api/equipo4/integration/transfers" (@{
  from_location_id=$LocationId; to_location_id=$LocationId; reason="TEST"
  idempotency_key="tr-$ts"
  items=@(@{ product_id=$ProductId; variant_id=$null; quantity=1 })
} | ConvertTo-Json -Depth 5 -Compress) | Out-Null

Write-Host "`n### TEST 4: WEBHOOK REFUNDED con items (auto-return) ###" -ForegroundColor Magenta
S POST "/api/equipo4/webhooks/payment-status" (@{
  payment_intent_id="PI-REFUND-$ts"; order_id="ORD-REFUND-$ts"
  business_id="BUS-CD-SOUV-001"; status="REFUNDED"; amount=150.00; currency="MXN"
  items=@(@{ product_id=$ProductId; variant_id=$null; location_id=$LocationId; quantity=1 })
  occurred_at=(Get-Date).ToUniversalTime().ToString("o")
} | ConvertTo-Json -Depth 5 -Compress) | Out-Null

Write-Host "`n=== FIN ===" -ForegroundColor Green