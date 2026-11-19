<x-mail::message>
# Bonjour {{ $firstName }},

La chorale vient de créer votre compte Kantica. Vous pouvez vous connecter dès maintenant avec les identifiants suivants.

<x-mail::panel>
**Adresse email :** {{ $mail }}

**Mot de passe :** {{ $password }}
</x-mail::panel>

Pour plus de sécurité, n'hésitez pas à changer votre mot de passe après votre première connexion.

Si vous n'attendiez pas ce message, vous pouvez l'ignorer.
</x-mail::message>
