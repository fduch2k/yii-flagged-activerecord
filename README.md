# TSFlaggedActiveRecord for Yii 1.1.x

Extends CActiveRecord class to add bitflag fields operations.

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
    //...then publis it
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
$criteria = Article::model()->applyFlags(new CDbCriteria(), array('draft', '!deleted'));
```
