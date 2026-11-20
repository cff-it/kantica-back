@props(['url'])
{{-- Logo de la chorale : image jointe au mail par App\Listeners\EmbedMailLogo (référence `cid:logo-cff`). --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="cid:logo-cff" alt="Chorale Francophone Faravohitra" width="89" height="80" style="width: 89px; height: 80px; margin-top: 15px; margin-bottom: 10px;">
</a>
</td>
</tr>
