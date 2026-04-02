@component('mail::message')
# Activation de la double authentification (2FA)

Bonjour {{ $user->name }},

La double authentification (2FA) a été activée sur votre compte. À chaque connexion, vous devrez saisir un code à 6 chiffres généré par une application d'authentification (Google Authenticator, Authy, etc.).

## 1. Scanner le QR code

Scannez le QR code ci-dessous avec votre application d'authentification :

![QR Code 2FA]({{ $qrCodeUrl }})

## 2. Ou saisir la clé manuellement

Si vous ne pouvez pas scanner le QR code, entrez cette clé manuellement dans votre application :

**{{ $secretKey }}**

## 3. Codes de récupération

Des codes de récupération sont joints à cet email. Conservez-les en lieu sûr. Chaque code peut être utilisé une seule fois si vous n'avez pas accès à votre application d'authentification.

Merci,<br>
{{ config('app.name') }}
@endcomponent
