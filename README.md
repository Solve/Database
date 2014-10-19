Database
========

Database component for Solve framework

**Already done:**
```
QC
MysqlDBAdaptor
DBOperator
ModelOperator
ModelStructure

Model
ModelCollection
ModelRelation

SlugAbility
TranslateAbility
FilesAbility (+thumbnails)

Validation
```

**Need to be realized**
> SortAbility
> TimeTrackAbility
> TreeAbility
> HistoryAbility
> DynamicAbility

> Paginator

### Sample model structure
**Brand:**
```yaml
table: brands
columns:
  id:
    type: 'int(11) unsigned'
    auto_increment: true
  title:
    type: varchar(255)
indexes:
  primary:
    columns:
      - id
relations:
  products: {  }
# here we have autodetect for model, for relation type and related field names
```
**Category:**
```yaml
table: categories
columns:
  id:
    type: 'int(11) unsigned'
    auto_increment: true
  title:
    type: varchar(255)
indexes:
  primary:
    columns:
      - id
relations:
  products: {  }
```

**Product:**
```yaml
table: products
columns:
  id:
    type: 'int(11) unsigned'
    auto_increment: true
  title:
    type: varchar(255)
  id_brand:
    type: 'int(11) unsigned'
indexes:
  primary:
    columns:
      - id
relations:
  brand: {  }
  categories: {  }
```

**Simple Operations:**
```php
$product = Product::loadOne(1);
$product->title = 'Macbook air';
$product->save();

$product = new Product();
$product->title = 'Macbook pro'
$product->save();

$list = Product::loadList(QC::create()->where('id < :d', 3));
//$list->loadRelated('brand'); - optional
echo $list->getFirst()->brand->id;
$list->getFirst()->setRelatedBrand(1); // set related by id
```