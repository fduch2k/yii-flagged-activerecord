# TSFlaggedActiveRecord for Yii 1.1.x

Extends CActiveRecord class to add bitflag fields operations. [Changelog](#Changelog)

## Installation
The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist fduch2k/yii-flagged-activerecord "*"
```

or add

```json
"fduch2k/yii-flagged-activerecord": "*"
```

to the require section of your composer.json.

## Usage

```php
class Article extends TSFlaggedActiveRecord 
{
    //...
    // By default flag field has name 'flags', you can override it 
    // if your prefer other name
    
    // public $flagsField = 'flags';

    // By default flags values without specified bit computed automatically 
    // (draft => 1, published => 2, deleted => 128)
    public function flags() 
    {
        return array(
            'draft',
            'published',
            'deleted' => 128
        );
    }
    
    // Flag labels uses in interface messages
    // By default an flag label is generated using
    // CModel::generateAttributeLabel
    public function flagLabels()
    {
        return array(
            'deleted'=>'Removed'
        );
    }

}
```

Now you can use it in you code:
### Scopes
```php

// Find all published articles
$articles = Article::model()->published()->findAll();

// or all drafts
$articles = Article::model()->withFlag('draft')->findAll();

// or deleted drafts
$articles = Article::model()->withFlag('draft, deleted')->findAll();

// or not deleted
$articles = Article::model()->withoutFlag('deleted')->findAll();
```

### Flag getters/setters
```php
$article = Article::model()->findByPk(10);
// Check if article is not deleted...
if ($article->isDeleted === false) {
    //...then publish it
    $article->isPublished = true;
}
$article->save();
```

### Getting flag value
```php
echo Article::model()->getFlag('deleted'); // outputs 128
```

### Apply flag conditions to criteria
```php
// get criteria to find not deleted article drafts
$criteria = Article::model()->applyFlags(new CDbCriteria(), array('draft', '!deleted'));
```

## Changelog

0.2.0 / 2014-11-21
==================
 * Overrides getAttributes and setAttributes methods to cover flag functionality
 * Added getFlagNames method
 * Method setFlag now can correctly work with boolean string 'true' 'false' (string that eqaul to 'true' is true othewise is false)
 * Added osx specific files to ignore

