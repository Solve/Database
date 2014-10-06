Database
========

Database component for Solve framework

### Sample model structure
**Product:**
```yaml
table: products
columns:
  id:
    type: 'int(11) unsigned'
    auto_increment: true
  title:
    type: varchar(255)
  id_category:
    type: 'int(11) unsigned'
indexes:
  primary:
    columns:
      - id
relations:
  category: {  }
  category_title:
    table: categories
    fields:
      - title
    use: title
    type: many_to_one
    local_field: id_category
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