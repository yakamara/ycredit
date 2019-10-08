<?php

namespace YCredit;

class Credit
{
    protected $media = [];
    protected $metaInfoField = 'title';
    protected $yformFields = [];


    public function __construct()
    {
    }

    protected function setMetaInfoField($value)
    {
        if (!\rex_addon::get('metainfo')->isAvailable()) {
            throw new \rex_exception('AddOn MetaInfo is not available!');
        }

        $handler = new \rex_metainfo_table_manager(\rex::getTable('media'));
        if (!$handler->hasColumn($value)) {
            throw new \rex_exception('MetaInfo Field "'.$value.'" does not exist!');
        }

        $this->metaInfoField = $value;
        return $this;
    }

    protected function withYForm()
    {
        if (!\rex_plugin::get('yform', 'manager')->isAvailable()) {
            throw new \rex_exception('AddOn YForm is not available!');
        }


        $sql = \rex_sql::factory();
        $sql->setQuery('SELECT * FROM `'.\rex_yform_manager_field::table().'` LIMIT 0');
        $columns = $sql->getFieldnames();
        $select = in_array('multiple', $columns) ? ', `multiple`' : '';
        $this->yformFields = $sql->getArray('SELECT `table_name`, `name`'.$select.' FROM `'.\rex_yform_manager_field::table().'` WHERE `type_id`="value" AND `type_name` IN("be_media","mediafile")');

        return $this;
    }

    protected function fetch()
    {
        $sql = \rex_sql::factory();
        $files = $sql->getArray('SELECT `filename`, '.$sql->escapeIdentifier($this->metaInfoField).' FROM '.\rex::getTable('media').' WHERE '.$sql->escapeIdentifier($this->metaInfoField).' != ""');
        if (!count($files)) {
            return null;
        }

        $collection = [];
        foreach ($files as $file) {
            $media = \rex_media::get($file['filename']);
            if (!$media) {
                continue;
            }
            $articles = [];
            $articles += $this->isUseInSlices($media);
            $articles += $this->isUseInMetainfoFields($media);

            if (count($this->yformFields)) {
                $articles += $this->isUseInYFormTables($media);
            }

            $collection[] = ['media' => $media, 'articles' => $articles];
        }


        return $collection;
    }

    protected function isUseInSlices(\rex_media $media)
    {
        $sql = \rex_sql::factory();

        // FIXME move structure stuff into structure addon
        $values = [];
        for ($i = 1; $i < 21; ++$i) {
            $values[] = 'value' . $i . ' REGEXP ' . $sql->escape('(^|[^[:alnum:]+_-])'.$media->getFileName());
        }

        $files = [];
        $filelists = [];
        $escapedFilename = $sql->escape($media->getFileName());
        for ($i = 1; $i < 11; ++$i) {
            $files[] = 'media' . $i . ' = ' . $escapedFilename;
            $filelists[] = 'FIND_IN_SET(' . $escapedFilename . ', medialist' . $i . ')';
        }

        $where = '';
        $where .= implode(' OR ', $files) . ' OR ';
        $where .= implode(' OR ', $filelists) . ' OR ';
        $where .= implode(' OR ', $values);

        if (\rex_plugin::get('bloecks', 'status')->isAvailable()) {
            $where = 'status = 1 AND ('.$where.')';
        }

        $query = 'SELECT DISTINCT article_id, clang_id FROM '.\rex::getTable('article_slice').' WHERE '.$where;

        $slices = $sql->getArray($query);

        $rows = $sql->getRows();
        if (0 == $rows) {
            return [];
        }

        $articles = [];
        foreach ($slices as $slice) {
            $articles[] = \rex_article::get($slice['article_id'], $slice['clang_id']);
        }

        return $articles;
    }

    protected function isUseInMetainfoFields(\rex_media $media)
    {
        $sql = \rex_sql::factory();
        $sql->setQuery('SELECT `name`, `type_id` FROM `'.\rex::getTable('metainfo_field').'` WHERE `type_id` IN(6,7)');

        $rows = $sql->getRows();
        if (0 == $rows) {
            return [];
        }

        $where = [
            'articles' => [],
            'media' => [],
            'clangs' => [],
        ];
        $escapedFilename = $sql->escape($media->getFileName());
        for ($i = 0; $i < $rows; ++$i) {
            $name = $sql->getValue('name');
            $prefix = \rex_metainfo_meta_prefix($name);
            if (\rex_metainfo_media_handler::PREFIX === $prefix) {
                $key = 'media';
            } elseif (\rex_metainfo_clang_handler::PREFIX === $prefix) {
                $key = 'clangs';
            } else {
                $key = 'articles';
            }
            switch ($sql->getValue('type_id')) {
                case '6':
                    $where[$key][] = $sql->escapeIdentifier($name) . ' = ' . $escapedFilename;
                    break;
                case '7':
                    $where[$key][] = 'FIND_IN_SET(' . $escapedFilename . ', ' . $sql->escapeIdentifier($name)  . ')';
                    break;
                default:
                    throw new \rex_exception('Unexpected fieldtype "' . $sql->getValue('type_id') . '"!');
            }
            $sql->next();
        }

        $articles = [];
        if (!empty($where['articles'])) {
            $sql->setQuery('SELECT id, clang_id, parent_id, name, catname, startarticle FROM '.\rex::getTable('article').' WHERE '.implode(' OR ', $where['articles']));
            if ($sql->getRows() > 0) {
                foreach ($sql->getArray() as $article) {
                    $articles[] = \rex_article::get($article['id'], $article['clang_id']);
                }
            }
        }

        // @Todo Medien in Medien berücksichtigen
        //$media = '';
        //if (!empty($where['media'])) {
        //    $sql->setQuery('SELECT id, filename, category_id FROM ' . rex::getTablePrefix() . 'media WHERE ' . implode(' OR ', $where['media']));
        //    if ($sql->getRows() > 0) {
        //        foreach ($sql->getArray() as $med_arr) {
        //            $id = $med_arr['id'];
        //            $filename = $med_arr['filename'];
        //            $cat_id = $med_arr['category_id'];
        //            $media .= '<li><a href="' . rex_url::backendPage('mediapool/detail', ['file_id' => $id, 'rex_file_category' => $cat_id]) . '">' . $filename . '</a></li>';
        //        }
        //        if ('' != $media) {
        //            $warning[] = rex_i18n::msg('minfo_media_in_use_med') . '<br /><ul>' . $media . '</ul>';
        //        }
        //    }
        //}

        return $articles;
    }

    protected function isUseInYFormTables(\rex_media $media)
    {
        $sql = \rex_sql::factory();

        $tables = [];
        $escapedFilename = $sql->escape($media->getFileName());
        foreach ($this->yformFields as $field) {
            $tableName = $field['table_name'];
            $condition = $sql->escapeIdentifier($field['name']).' = '.$escapedFilename;
            if (isset($field['multiple']) && $field['multiple'] == 1) {
                $condition = 'FIND_IN_SET('.$escapedFilename.', '.$sql->escapeIdentifier($field['name']).')';
            }
            $tables[$tableName][] = $condition;
        }

        $articles = [];
        foreach ($tables as $tableName => $conditions) {
            $items = $sql->getArray('SELECT `id` FROM '.$tableName.' WHERE '.implode(' OR ', $conditions));
            if (count($items)) {
                foreach ($items as $item) {
                    $sqlData = \rex_sql::factory();
                    $sqlData->setQuery('SELECT `name`, `description` FROM `'.\rex_yform_manager_table::table().'` WHERE `table_name` = "'.$tableName.'"');
                    $moduleId = $sqlData->getValue('description');

                    $sqlSlices = \rex_sql::factory();
                    $slices = $sqlSlices->getArray('SELECT article_id, clang_id FROM '.\rex::getTable('article_slice') . ' WHERE module_id = :moduleId', ['moduleId' => $moduleId]);

                    $articles[] = \rex_article::get($slices[0]['article_id'], $slices[0]['clang_id']);
                }
            }
        }

        return $articles;
    }

    public static function findAll($metaInfoField = null)
    {
        $instance = new self();

        if (null !== $metaInfoField) {
            $instance->setMetaInfoField($metaInfoField);
        }

        $instance->withYForm();
        return $instance->fetch();
    }
}
