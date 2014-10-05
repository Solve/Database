Database
========

Database component for Solve framework

### Model Product sample
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
  category: true
# here we have autodetect for model, for relation type and related field names
```
