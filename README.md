simple-codeigniter-orm-api
==========================

A very basic ORM like model extension for codeigniter. Aims to create tables in a consistent manner that is compatible with the Grocery_CRUD library without need to use a Db management tool. It will not entirely replace the tedium of phpmyadmin and friends but should greatly reduce the need for repeating basic, standard tasks.

Allows stringing of table functions for easy inline table and relationship creation.

NB: Currently only supports MySQL

Files
=====

	config/orm_api.php 
	core/MY_Model.php
	helpers/reserve_word_helper.php	

Tables
======

All tables are created with primary key of "id", "created" date which is auto set as current timestamp, a "modified" date that is updated using a trigger and an "active" boolean flag.


Relationships
============
1:1
---

1:1 relationships are created by a foreign key column in the parent table.

1:*
---

1:* relationships are created using a relationship table with the table name structured as:

  	parent_has_child

A composite unique constraint ensures only unique records are created. 

Usage
=====

Before use a database must be created and the config file database.php completed as normal and the CI database library loaded (usually by including in the autoload.php config)

These functions are designed to be used in your model code but can be used in the controller by initialising a model as normal using:

	$this->load->model('your_model');

And then stringing the ORM functions like:

	$this->your_model->init('table_name');

In order to initialise a table (has to be done before any other function call) we use the init() function like this:

	$this->init('table_name');

This will create a table if it does not exist and set the protected variable $table.

To insert data create your model function something like this:

	//$data=['string_column'=>'your string','integer_column'=>999,'boolean_column'=>true]

        public function input_some_data($data){
            $this->init('table_name')->insert($data);
        }

Data types are auto detected using following rules

- Depending on a strings length it will be created either as VARCHAR(50),VARCHAR(250) OR TEXT
- Dates must be in MySQL friendly format Y-d-m H:i:s or will be treated as strings
- Booleans will be created as TINYINT(1) 
- Default to VARCHAR(50)

All values are by default nullable.

A successful insert will also set the protected id model variable and allow the data to be returned or the record to be related to another table record like this:

        public function input_some_data($data,$data2){
            	$child_id = $this->init('table_name')->insert($data)->id;
	    	$this->init('table_name_2')->insert($data)->relate('table_name')->link('table_name',$child_id);
        }

Checking for existing tables and keys obviously has an overhead so in production you should change the config file and set:

	$config["fluid_schema"]=false;

API Functions
=============

init($table_name) - Sets the model table variable and constructs a new table if one doesnt exist

id($id) - Sets the model id variable - this is set during inserts and updates implicitly

set_fluid(bool) - Sets the fluid schema flag. This is set on construct from the config file

insert($data) - Inserts a row of data using CI active record. If columns dont exist they are created

update($data) - Same as insert but requires the id(id) to be set first

relate($child_table,$many=false) - creates a relationship between the active table and the child table param. Defaults to a one:one relationship. Set $many to true to create a one:many

unrelate($child_table) - removes a relationship between the current active table and the child table param

link($child_table,$id) - creates a relationship between the current active record and a child record. relate() must be called first

grab() -Retrieves records with any related data. If $id is set a single record will be retrieved. If join is set to false only the parent record will be returned otherwise all related records will also be returned. The $where param accepts an associative array of where conditions as per the CI docs


