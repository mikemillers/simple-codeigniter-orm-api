<?php
/**
 * A basic ORM that creates uknown tables,columns and relations on the fly
 *
 * Author: Mike Miller 2014
 */

class MY_Model extends CI_Model
{

    /* --------------------------------------------------------------
     * VARIABLES
     * ------------------------------------------------------------ 
     * 
     */
    
    
    protected $id = null;
    
    protected $table = null;
    
    protected $fluid = false;
    
    protected $data = null;
    
    public function __construct() {
        parent::__construct();
        
        $this->load->config('orm_api');
        
        $this->load->helper('orm_api');
        
        //set fluid using config value
        self::set_fluid();
    }
    
    /* --------------------------------------------------------------
     * init()
     * ------------------------------------------------------------ 
     * 
     * Setter function for protected var $table. 
     * 
     * Will create the table if it doesnt exist and the application config has fluid_schema=true
     * 
     */   
    public function init($table){
        $this->table = $table;
        if(!$this->db->query('SHOW TABLES LIKE ?',$this->table)->result_array()){
            if($this->config->item('fluid_schema')){
                self::put_table();
            }else{
                Throw new Exception('Table does not exist. Switch to fluid mode to create it.');
            }
        }
        
        return $this;
    }
    
    /* --------------------------------------------------------------
     * fluid()
     * ------------------------------------------------------------ 
     * 
     * Setter function for protected var $fluid. 
     * 
     * Can be used to set fluid on the fly otherwise will be called on construct and use the config value
     * 
     */   
    
    public function set_fluid($set=false){
        if($set){
            $this->fluid = $set;
        }else{
            $this->fluid = $this->config->item('fluid_schema');
        }
    }
    
 
    /* --------------------------------------------------------------
     * id()
     * ------------------------------------------------------------ 
     * 
     * Setter function for protected var $id. 
     * 
     * Needs to be used before using grab() or update()
     * 
     * Setting to null retrieves all values
     * 
     */ 
    public function id($id=null){
        $this->id = $id;
        return $this;
    }
    
 
    /* --------------------------------------------------------------
     * insert()
     * ------------------------------------------------------------ 
     * 
     * Inserts an associative array into the current initialized $table
     * 
     * If the fields do not exist it will create them.
     * 
     * Must have the table initialized before use
     * 
     */
    public function insert($data){
        
        if(empty($this->table)){
            Throw new exception("You must initialize with the table name first.");
        }
        
        if(!is_assoc($data)){
            Throw new exception("Your insert array must be associative.");
        }
        
        if($this->config->item('fluid_schema')){
            
            $ddl = false;
            $add="";
            
            foreach($data as $col=>$fact){
                if(!self::col_exists($col)){
                    if(is_reserved_word($col)){
                        Throw new exception("Do not use MySQL or PHP reserve word as column name");
                    }
                    
                    if(strpos($col,'fk_')!==false){
                        Throw new exception("Set FK relationships using the relation() function before insert().");
                    }
                    
                    $ddl[$col]=$fact;
                }
            }
            //ALTER TABLE `test` ADD `name` INT NOT NULL , ADD `fk` INT NOT NULL ;
            if($ddl){
                foreach($ddl as $col=>$fact){
                    
                    $add.=" ADD ".$col.$this->datatype($fact);
                }
                
                $add = rtrim($add,",");
                
                $this->db->query("ALTER TABLE ".$this->table.$add);
            }
        
        }
        $this->db->insert($this->table,$data);
        $this->id($this->db->insert_id());
        return $this;
    }
    
    /* --------------------------------------------------------------
     * update()
     * ------------------------------------------------------------ 
     * 
     * Updates an associative array into the current initialized $table
     * 
     * If the fields do not exist it will create them.
     * 
     * Must have the table and id initialized before use
     * 
     */
    public function update($data){
        
        if(empty($this->table)){
            Throw new exception("You must initialize with the table name first.");
        }
        
        if(empty($this->id)){
            Throw new exception("You must set the id using id() or insert() before updating.");
        }
        
        if($this->config->item('fluid_schema')){
            
            $this->load->helper('reserve_word');
            $ddl = false;
            
            foreach($data as $col=>$fact){
                if(!self::col_exists($col)){
                    if(is_reserved_word($col)){
                        Throw new exception("Do not use MySQL or PHP reserve word as column name");
                    }
                    if(strpos($col,'fk_')!==false){
                        Throw new exception("Set FK relationships using the relation() function before update().");
                    }
                    
                    $ddl[$col]=$fact;
                }
            }
            //ALTER TABLE `test` ADD `name` INT NOT NULL , ADD `fk` INT NOT NULL ;
            if($ddl){
                foreach($ddl as $col=>$fact){
                    
                    $add.=" ADD ".$col.$this->datatype($fact);
                }
                
                $add = rtrim($add,",");
                
                $this->db->query("ALTER TABLE ".$this->table.$add);
            }
        
        }
        $this->db->where('id',$this->id);
        $this->db->update($this->table,$data);
        return $this;
    }
    
    /* --------------------------------------------------------------
     * relate()
     * ------------------------------------------------------------ 
     * 
     * Creates a relationship between currrently initialized table and the $table param
     * 
     * Defaults to a one:one. Set $many=true to create one:many
     * 
     */
    public function relate($table,$many=false){
        //check relation table exists if not create that as well
        
        $master = $this->table;
        if($many){
            $this->put_one_to_many_relation($this->table, $table);
        }else{
            $this->put_one_to_one_relation($this->table, $table);            
        }
        $this->table=$master;
        return $this;
    }
    
    /* --------------------------------------------------------------
     * unrelate()
     * ------------------------------------------------------------ 
     * 
     * Removes a relationship between currrently initialized table and the $table param
     * 
     */
    public function unrelate($table){
        //check relation table exists if not create that as well
        
        if(self::col_exists("fk_".$table."_id")){
            $this->db->query("ALTER TABLE `".$this->table."` DROP FOREIGN KEY `".$this->table."_".$table."_fk` ");
            $this->db->query("ALTER TABLE `".$this->table."` DROP `fk_".$table."_id`");
            
        }else{
            if(self::table_exists($this->table."_has_".$table)){
                $this->db->query("DROP TABLE `".$this->table."_has_".$table."`");
            }
        }
        return $this;
    }
    
    /* --------------------------------------------------------------
     * link()
     * ------------------------------------------------------------ 
     * 
     * Links records in tables which have relationships
     * 
     * 
     */
    public function link($table,$id){
        
        if(empty($this->id)){
            Throw new exception('You must set the id using id() before creating a link');
        }
        
        if(empty($this->table)){
            Throw new exception("You must initialize with the table name first.");
        }
        //if fk_exists in current table then 1-1 link 
        
        if(self::col_exists("fk_".$table."_id")){
            
            $this->db->where('id',$this->id);
            $data["fk_".$table."_id"]=$id;
            $this->db->update($this->table,$data);
            
        }elseif(self::table_exists($this->table."_has_".$table)){
            
            if(is_array($id)){
                foreach($id as $i){
                    $data[]=array(
                        "fk_".$this->table."_id"=>$this->id,
                        "fk_".$table."_id"=>$i
                    );
                }
                $this->db->insert_batch($this->table."_has_".$table,$data);
            }else{
                $data["fk_".$this->table."_id"]=$this->id;
                $data["fk_".$table."_id"]=$id;
                $this->db->insert($this->table."_has_".$table,$data);
            }
        }else{
            Throw new Exception('Relationship does not exist. Create using relate() first');
        }
        return $this;
    }
    
    /* --------------------------------------------------------------
     * grab()
     * ------------------------------------------------------------ 
     * 
     * Retrieves records with any related data. If $id is set a single record will be retrieved
     * 
     * If join is set to false only the parent record will be returned otherwise all related records will also be returned
     * 
     * The $where param accepts an associative array of where conditions as per the CI docs
     * https://ellislab.com/codeigniter/user-guide/database/active_record.html#select
     * 
     * 
     */
    public function grab($join=true,$where=null){
        
        $single_joins = null;
        $many_joins=null;
        
        if($join){
            $single_joins = self::has_fks();
            $many_joins = self::has_manys();
        }
        
        if(!empty($this->id)){
            $this->db->where('id',$this->id);
        }
        
        if(!empty($where)){
            if(is_assoc($where)){
                $this->db->where($where);
            }else{
                Throw new Exception('You must use an associative array for the $where param');
            }
        }
        
        foreach($this->db->get($this->table)->result() as $k=>$result){
            
            foreach($result as $col=>$fact){
                $this->data[$k][$col]=$fact;
            }
            
            if(!empty($single_joins)){
                foreach($single_joins as $join){
                    //$this->db->join($join,'fk_'.$join.'_id = '.$join.'.id','left');
                    //$select.=','.$join.'.* ';
                    $this->db->where('id',$this->data[$k]['fk_'.$join.'_id']);
                    $result = $this->db->get($join)->result_array();
                    
                    $col_k=0;
                    if(!empty($result[0])){
                        foreach($result[0] as $col=>$fact){
                            $this->data[$k][$join][$col_k][$col]=$fact;

                        }
                    }
                    $col_k++;
                    unset($this->data[$k]['fk_'.$join.'_id']);
                }
            }
            
            if(!empty($many_joins)){
                
                foreach($many_joins as $join){
                    
                    $child_table = substr($join,  strpos($join, 'has_')+4);
                    
                    $result = $this->db->query("SELECT `".$child_table."`.* FROM `".$join."` 
                        LEFT JOIN `".$child_table."` ON `fk_".$child_table."_id`= `".$child_table."`.`id` 
                            WHERE `fk_".$this->table."_id` = ?",[$this->data[$k]['id']])->result_array();
                    
                    if(!empty($result)){
                        $this->data[$k][$child_table]=$result;
                    }
                }
            }
        }
        return $this;
    }
     
    
    /* ------------------------------------------------------------
     * PRIVATE FUNCTIONS
     * ------------------------------------------------------------ 
     */
    private function put_table($cms=true){
        
            if(is_reserved_word($this->table)){
                Throw new exception("Do not use MySQL or PHP reserve word as table name");
            }
            //create the table with the custom columns
            $response= $this->db->query("CREATE TABLE IF NOT EXISTS `".$this->table."` (
                            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                            `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                            `active` BOOLEAN NOT NULL DEFAULT TRUE,
                            PRIMARY KEY (`id`)
                          ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

            
            //add the trigger to set the modified date on table update
            self::put_trigger();
            
            //add to the schema xml document
            return $response;
        }
        
    private function put_columns($columns){
        
        $this->db->query("ALTER TABLE ".$this->table.  implode(",", $columns));
        
        return true;
    }

    private function put_trigger(){
        $this->db->query("DROP TRIGGER IF EXISTS `update_".$this->table."_trigger`");

        return $this->db->query("CREATE TRIGGER `update_".$this->table."_trigger` BEFORE UPDATE 
            ON `".$this->table."`
                 FOR EACH ROW SET NEW.`modified` = NOW();");

        }


    private function put_one_to_one_relation($table1,$table2){
        
        if(!self::col_exists("fk_".$table2."_id")){
            $this->db->query("ALTER TABLE `".$table1."` ADD `fk_".$table2."_id` INT( 11 ) UNSIGNED NULL AFTER `id` ;");
            $this->db->query("ALTER TABLE `".$table1."` ADD INDEX ( `fk_".$table2."_id` ) ;");
            $this->db->query("ALTER TABLE `".$table1."` 
                ADD CONSTRAINT `".$table1."_".$table2."_fk` FOREIGN KEY ( `fk_".$table2."_id`  ) REFERENCES `".$table2."` (
                `id`
                ) ON DELETE RESTRICT ON UPDATE RESTRICT ;");
        }
        return true;
    }    

    private function put_one_to_many_relation($table1,$table2){

        //check relation table exists or not
        $columns = array(
                " ADD fk_".$table2."_id INT UNSIGNED NOT NULL AFTER `id`",
                " ADD fk_".$table1."_id INT UNSIGNED NOT NULL AFTER `id`"
            );

        $this->table=$table1."_has_".$table2;
        
        if(self::table_exists($this->table)){
            return true;
        }
        
        $this->put_table(false);
        
        $this->put_columns($columns);

        foreach(func_get_args() as $table_name){
                $this->db->query("ALTER TABLE `".$this->table."` ADD INDEX ( `fk_".$table_name."_id` ) ;");

                $this->db->query("ALTER TABLE `".$this->table."` 
                    ADD FOREIGN KEY ( `fk_".$table_name."_id` ) REFERENCES `".$table_name."` (
                    `id`
                    ) ON DELETE RESTRICT ON UPDATE RESTRICT ;");
            }
            //add the composite unique constraint
        $this->db->query("ALTER TABLE `".$this->table."` ADD UNIQUE (`fk_".$table1."_id` ,`fk_".$table2."_id`);");
        return true;
    }
        
    private function datatype($fact){
        
        switch($fact){            
            case is_bool($fact):
                return " TINYINT(1) AFTER `id`,";

            case is_date($fact): 
                return " TIMESTAMP DEFAULT '0000-00-00 00:00:00' AFTER `id`,";

            case is_string($fact) && (strlen($fact)<50):
                return " VARCHAR(50) AFTER `id`,";

            case is_string($fact) && (strlen($fact)<250):
                return " VARCHAR(250) AFTER `id`,";

            case is_string($fact):
                return " TEXT AFTER `id`,";

            case is_int($fact):
                return " INT AFTER `id`,";

            default:
                return " VARCHAR(50) AFTER `id`";
                
        }
    }
    
    private function has_manys(){
        $results = $this->db->query("SHOW TABLES LIKE ?",$this->table."_has%")->result_array();
        $joins = null;
        foreach($results as $result){
            $joins[]=$result["Tables_in_".$this->db->database." (".$this->table."_has%)"];
        }
        return !empty($joins)?$joins:false;
    }
    
    private function has_fks(){
        $result = $this->db->query("SHOW COLUMNS FROM ".$this->table." LIKE 'fk_%'")->result_array();
        if($result){
            foreach($result as $field){
                $joins[]= str_replace(array('fk_','_id'), '', $field['Field']);
            }
            return $joins;
        }
        
        return false;
    }
    
    private function col_exists($col){
        if($this->db->query("SHOW COLUMNS FROM ".$this->table." LIKE ?",$col)->result_array()){
            return true;
        }
        return false;
    }
    
    private function table_exists($table){
        if($this->db->query("SHOW TABLES LIKE ?",$table)->result_array()){
            return true;
        }
        return false;
    }
}
