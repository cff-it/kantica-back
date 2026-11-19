<x-mail::message>
# Bonjour {{ $firstName }},

La chorale vient de créer votre compte Kantica. Vous pouvez vous connecter dès maintenant avec les identifiants suivants.

<x-mail::panel>
**Adresse email :** {{ $mail }}

**Mot de passe :** {{ $password }}
</x-mail::panel>

Si vous n'attendiez pas ce message, vous pouvez l'ignorer.
</x-mail::message>
