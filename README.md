YCredit - Foto-/Bildnachweise
================================================================================

## Hinweise
- Metainfo `art_` unf `cat_` Felder werden beachtet, jedoch keine `med_` Felder
- Modul-Id in der YForm-Tabelle als Beschreibung hinterlegen. Dadurch werden die Strukturartikel gefunden und können verlinkt werden. Dies funktioniert nur, wenn keine speziellen Einschränkungen (where, status etc.) im Module-Code genutzt werden. Sollte dies der Fall sein, dann am besten in der Modulausgabe der Bildnachweise nicht zum Artikel verlinken.
- Blöcks Status wird beachtet 


## Modulausgabe

```php
<?php
use YCredit\Credit;

$creditField = 'med_description';

$credits = Credit::findAll($creditField);

if (!count($credits)) {
    return;
}

$list = [];
foreach ($credits as $credit) {
    /* @var $media rex_media */
    $media = $credit['media'];

    /* @var $articles rex_article[] */
    $articles = $credit['articles'];

    // Wenn keine Artikel vorhanden sind, dann das Bild nicht ausgeben
    if (empty($articles)) {
        continue;
    }

    // Nur zu einem Artikel verlinken
    $article = $articles[0];

    $list[] = sprintf(
        '<img src="/images/rex_mediapool_detail/%s" alt="" /><br />
        Bildnachweis: %s<br />
        <a href="%s">Auf dieser Seite verwendet</a>'
        , $media->getFileName(), $media->getValue($creditField), $article->getUrl());
}

echo implode('', $list);

```


- - - - - - - - - - - - - - - - - - - -

## Bugtracker

Du hast einen Fehler gefunden oder ein nettes Feature parat? [Lege ein Issue an](https://github.com/yakamara/ycredit/issues). Bevor du ein neues Issue erstellts, suche bitte ob bereits eines mit deinem Anliegen existiert und lese die [Issue Guidelines (englisch)](https://github.com/necolas/issue-guidelines) von [Nicolas Gallagher](https://github.com/necolas/).


## Changelog

siehe [CHANGELOG.md](https://github.com/yakamara/ycredit/blob/master/CHANGELOG.md)

## Lizenz

siehe [LICENSE.md](https://github.com/yakamara/ycredit/blob/master/LICENSE.md)
