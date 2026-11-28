<?php

namespace App\Enums;

// Type d'une section de cantique (`song_sections.type`). D'autres types ("antienne", "texte complet" du programme de messe,
// voir US-20) seront à ajouter ici s'ils sont validés.
enum SectionType: string
{
    case Verse = 'couplet';
    case Chorus = 'refrain';
    case Bridge = 'pont';
}
