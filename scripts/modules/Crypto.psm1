#Requires -Version 5.1
<#
.SYNOPSIS
    Encrypt/decrypt file dengan AES-256-CBC
.DESCRIPTION
    Format: [IV 16 bytes][ciphertext]
    Key: 32-byte hex (64 karakter)
    IV: random 16 bytes per encryption
#>

function Parse-HexKey {
    param([string]$KeyHex)
    
    # Sanitasi: trim whitespace, newline, CRLF
    $clean = $KeyHex.Trim() -replace "\s+", ""
    
    # Validasi: harus 64 karakter hex
    if ($clean.Length -ne 64) {
        throw "Key harus 64 karakter hex (32 bytes), dapat $($clean.Length) karakter"
    }
    if ($clean -notmatch "^[0-9a-fA-F]{64}$") {
        throw "Key mengandung karakter non-hex"
    }
    
    # Parse ke byte array
    $key = [byte[]]::new(32)
    for ($i = 0; $i -lt 32; $i++) {
        $key[$i] = [Convert]::ToByte($clean.Substring($i * 2, 2), 16)
    }
    return $key
}

function Protect-Backup {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory)] [string]$InputFile,
        [Parameter(Mandatory)] [string]$OutputFile,
        [Parameter(Mandatory)] [string]$KeyHex
    )
    
    $key = Parse-HexKey $KeyHex
    
    $aes = [System.Security.Cryptography.Aes]::Create()
    $aes.Key = $key
    $aes.Mode = [System.Security.Cryptography.CipherMode]::CBC
    $aes.Padding = [System.Security.Cryptography.PaddingMode]::PKCS7
    $aes.GenerateIV()
    
    $encryptor = $aes.CreateEncryptor()
    $input = [System.IO.File]::ReadAllBytes($InputFile)
    $ciphertext = $encryptor.TransformFinalBlock($input, 0, $input.Length)
    
    $output = $aes.IV + $ciphertext
    [System.IO.File]::WriteAllBytes($OutputFile, $output)
    
    $aes.Dispose()
    Write-Host "? Encrypted: $OutputFile ($([math]::Round($output.Length/1KB,1)) KB)"
}

function Unprotect-Backup {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory)] [string]$InputFile,
        [Parameter(Mandatory)] [string]$OutputFile,
        [Parameter(Mandatory)] [string]$KeyHex
    )
    
    $key = Parse-HexKey $KeyHex
    
    $fileBytes = [System.IO.File]::ReadAllBytes($InputFile)
    if ($fileBytes.Length -lt 16) { throw "File too small, not encrypted" }
    
    $iv = $fileBytes[0..15]
    $ciphertext = $fileBytes[16..($fileBytes.Length - 1)]
    
    $aes = [System.Security.Cryptography.Aes]::Create()
    $aes.Key = $key
    $aes.IV = $iv
    $aes.Mode = [System.Security.Cryptography.CipherMode]::CBC
    $aes.Padding = [System.Security.Cryptography.PaddingMode]::PKCS7
    
    $decryptor = $aes.CreateDecryptor()
    $plaintext = $decryptor.TransformFinalBlock($ciphertext, 0, $ciphertext.Length)
    
    [System.IO.File]::WriteAllBytes($OutputFile, $plaintext)
    $aes.Dispose()
    
    Write-Host "? Decrypted: $OutputFile ($([math]::Round($plaintext.Length/1KB,1)) KB)"
}

Export-ModuleMember -Function Protect-Backup, Unprotect-Backup