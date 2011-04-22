<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Comics extends Admin_Controller {

	function __construct()
	{
		parent::__construct();
                $this->ion_auth->logged_in() or redirect('auth/login');
                $this->ion_auth->is_admin() or redirect('admin');
                $this->ion_auth->is_admin() or die(1);
                $this->load->model('files_model');
                $this->load->library('form_validation');
                $this->load->library('pagination');
                $this->viewdata['controller_title'] = "Comics";
        }

        function index()
        {
            redirect('/admin/comics/comics');
        }

	function manage($page = 1)
        {
            $this->viewdata["function_title"] = "manage";

            $comics = new Comic();
            $comics->order_by('name', 'ASC');
            $comics->get_paged_iterated($page, 10);
            $data["comics"] = $comics;

            $this->viewdata["main_content_view"] = $this->load->view("admin/comics/comics.php", $data, TRUE);
            $this->load->view("admin/default.php", $this->viewdata);
        }



        function comic($stub = NULL, $chapter_id = "")
        {
            $comic = new Comic();
            $comic->where("stub", $stub)->get();
            if($comic->result_count() == 0)
            {
                set_notice('warn', 'The comic you looked for does not exist.');
                $this->manage();
                return false;
            }

            $this->viewdata["function_title"] = '<a href="'.site_url('admin/comics/comic').'/'.$comic->stub.'">'.$comic->name.'</a>';
            $data["comic"] = $comic;

            if($chapter_id != "")
            {
				if($this->input->post())
				{
					$chapter = new Chapter();
					if($chapter->update_chapter_db($this->input->post()))
					{
						redirect('/admin/comics/comic/'.$comic->stub.'/'.$chapter->id);
					}
				}
				
                $chapter = new Chapter();
                $chapter->where('id', $chapter_id);
				$data["chapter"] = $chapter->get();
				
                $team = new Team();                
				$teams = $team->get_teams_name($chapter->team_id, $chapter->joint_id);
				
				$table = ormer($chapter);
				
				$table[] = array(
					'Teams',
					array(
						'name' => 'team',
						'type' => 'input',
						'value' => $teams
					)
				);
				
				$table = tabler($table);
				
				$data["table"] = $table;
				
                
                $this->viewdata["extra_title"][] = (($chapter->name != "") ? $chapter->name : $chapter->chapter.".".$chapter->subchapter);

                
                $data["pages"] = $chapter->get_pages();

                $this->viewdata["main_content_view"] = $this->load->view("admin/comics/chapter.php", $data, TRUE);
                $this->load->view("admin/default.php", $this->viewdata);
                return true;
            }

            $chapters = new Chapter();
            $chapters->where('comic_id', $comic->id)->include_related('team')
                   ->order_by('chapter', 'DESC')->order_by('subchapter', 'DESC')->get();
            foreach($chapters->all as $key => $item)
            {
                $temp = array();
                if ($item->joint_id != 0)
                {
                    $teams = new Team();
                    $teams->where_related_joint('id', $item->joint);
                    $chapters[$key]->joint = $teams;
                }
            }
			
            $data["chapters"] = $chapters;
			
			$comic->thumbnail = $comic->get_thumb();
			
			$table = ormer($comic);
			
			
			$table = tabler($table);
			$data['table'] = $table;

            $this->viewdata["main_content_view"] = $this->load->view("admin/comics/comic.php", $data, TRUE);
            $this->load->view("admin/default.php", $this->viewdata);
        }


        function add_new($stub = "")
        {
			$this->viewdata["function_title"] = "Add new";
			
			if($stub != "")
			{
				$comic = new Comic();
				$comic->where('stub', $stub)->get();
				$this->viewdata["extra_title"][] = "Chapter in ".$comic->name;
				$chapter = new Chapter();
				$chapter->comic_id = $comic->id;
				
				$table = ormer($chapter);
				
				$table[] = array(
					'Teams',
					array(
						'name' => 'team',
						'type' => 'input',
						'value' => array()
					)
				);
				
				$table = tabler($table, FALSE, TRUE);
				
				$data["table"] = $table;
				
				$this->viewdata["main_content_view"] = $this->load->view("admin/form.php", $data, TRUE);
				$this->load->view("admin/default.php", $this->viewdata);
				return true;
			}
            
			$this->viewdata["extra_title"][] = "Comic";
            $this->viewdata["main_content_view"] = $this->load->view("admin/comics/add_new.php",NULL, TRUE);
            $this->load->view("admin/default.php", $this->viewdata);
        }

        function add($type)
        {
            switch($type){
                case "comic":
                    $name = $this->input->post('name');
                    $hidden = $this->input->post('hidden');
                    $description = $this->input->post('description');

                    $config['upload_path'] = 'content/cache/';
                    $config['allowed_types'] = 'jpg|png|gif';
                    $this->load->library('upload', $config);
                    if ( ! $this->upload->do_upload())
                    {
                        $did_upload = FALSE;
                    }
                    else
                    {
                        $data = $this->upload->data();
                        $did_upload = TRUE;
                    }

                    $comic = new Comic();
                    if (!$comic->add_comic($name, $hidden, $description))
                    {
                        $this->add_new();
                    }
                    else
                    {
                        $comics = new Comic();
                        $comics->where("id", $comic->id)->get();

                        if($did_upload)
                        {
                            if(!$this->files_model->comic_thumb($comics, $data))
                            {
                                log_message("error", "Controller: comics.php/add: image failed being added to folder");
                            }
                            if ( ! unlink($data["full_path"]))
                            {
                                set_notice('error', 'comics.php/add: couldn\'t remove cache file '.$data["full_path"]);
                                return false;
                            }
                            
                        }

                        redirect("admin/comics/comic/".$comics->stub);
                    }
                    break;					
            }

        }

        function upload($type)
        {
            $config['upload_path'] = 'content/cache/';

            switch($type)
            {
                case "compressed_chapter":
                    $config['allowed_types'] = 'zip';
                    $this->load->library('upload', $config);
                    if ( ! $this->upload->do_upload())
                    {
                        print_r($error = array('error' => $this->upload->display_errors()));
                        //$this->load->view('upload_form', $error);
                        return false;
                    }
                    else
                    {
                        $data = $this->upload->data();
                        $data["chapter_id"] = $this->input->post('chapter_id');
                        $data["overwrite"] = $this->input->post('overwrite');
                        $this->files_model->compressed_chapter($data);
                    }
                    if ( ! unlink($data["full_path"]))
                    {
                       set_notice('error', 'comics.php/upload: couldn\'t remove cache file '.$data["full_path"]);
                       return false;
                    }
                    $chapter = new Chapter();
                    $chapter->where('id', $data["chapter_id"])->get();
                    $comic = new Comic();
                    $comic->where('id', $chapter->comic_id)->get();
                    redirect('admin/comics/comic/'.$comic->stub.'/'.$data["chapter_id"]);
                    break;
                case "page":
                    $config['allowed_types'] = 'gif|jpg|png';
                    $this->load->library('upload', $config);
                    if ( ! $this->upload->do_upload())
                    {
                        $error = array('error' => $this->upload->display_errors());
                        //$this->load->view('upload_form', $error);
                        return false;
                    }
                    break;
            }

            return true;
        }

        function remove($type, $id)
        {
            switch($type)
            {
                case("comic"):
                    $comic = new Comic();
                    $comic->where('id', $id)->get();
                    if(!$comic->remove_comic())
                    {
                        log_message("error", "Controller: comics.php/remove: failed comic removal");
                    }
                    flash_notice('notice','The comic '.$comic->name.' has been removed');
                    redirect("admin/comics/manage");
                    break;
                case("chapter"):
                    $chapter = new Chapter();
                    $chapter->where('id', $id)->get();
                    if(!$comic = $chapter->remove_chapter())
                    {
                        log_message("error", "Controller: comics.php/remove: failed chapter removal");
                    }
                    redirect("admin/comics/comic/".$comic->stub);
                    break;
                case("page"):
                    $page = new Page();
                    $page->where('id', $id)->get();
                    if(!$data = $page->remove_page())
                    {
                        log_message("error", "Controller: comics.php/remove: failed page removal");
                    }
                    redirect("admin/comics/comic/".$data["comic"]->stub."/".$data["chapter"]->id);
                    break;
            }
        }
}