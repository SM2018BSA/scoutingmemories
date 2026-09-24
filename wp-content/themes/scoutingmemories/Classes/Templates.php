<?php


class Templates
{


    //// Makes a bootstrap component with javascript controls
    //
    //  takes a special array of arguments

    //  array(array("title1", "body1"), array("title2", "body2"), array("title(n)", "body(n)"))

    //
    //

    public static function accordion($name = "name", $args = array(array("title1", "body1"), array("title2", "body2"), array("title3", "body3"))) {

        $html = '<div class="accordion accordion-flush" id="accordion_' . $name . '">';

        $i = 0;
        foreach ($args as $arg) {
            $title = $arg[0];
            $body = $arg[1];

            $html .= '<div class="accordion-item">';
            $html .= '<h2 class="accordion-header" id="flush-heading_'.$i.'">';
            $html .= '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapse_'.$i.'" aria-expanded="false" aria-controls="flush-collapse_'.$i.'">';
            $html .= $title;
            $html .= '</button></h2>';
            $html .= '<div id="flush-collapse_'.$i.'" class="accordion-collapse collapse" aria-labelledby="flush-heading_'.$i.'" data-bs-parent="#accordion_' . $name . '">';
            $html .= '<div class="accordion-body">';
            $html .= $body;
            $html .= '</div></div>';

            $i++;
        }


        $html .= '</div>';


        return $html;


    }



    //    ///////    ///////    ///////    ///////    ///////    /////
    //
    //          $objects = StateEntry::get_all();
    //          $object = StateEntry::get_one();
    //          $object_columns = array("id", "item_key", "name");
    //
    //
    //

    public static function show_object_table($title_object, $objects, $object_columns = null, $current_page = 1, $per_page = 10): string
    {

        $offset = $per_page * ($current_page -1);
        $title_object = Helpers::object_to_array($title_object);
        $objects = Helpers::object_to_array($objects);

        $objects = array_slice($objects, $offset, $per_page);

        if (!is_null($object_columns)) {
            foreach ($title_object as $property => $value) :
                if (is_array($value) || !in_array($property, $object_columns)) {
                    unset($title_object[$property]);
                }
            endforeach;

            foreach ($objects as $key => $object) :
                foreach ($object as $property => $value) :
                    if (is_array($value) || !in_array($property, $object_columns)) {
                        unset($objects[$key][$property]);
                    }
                endforeach;
            endforeach;
        }






        if (is_null($object_columns)) {
            return self::show_object_table_raw($title_object, $objects);

        } else {

            $html = '<div class="container"><table class="table">';
            $html .= '<thead>';
            foreach ($title_object as $property => $value) :
                $html .= '<th scope="col">' . $property . '</th>';
            endforeach;
            $html .= '</thead>';
            $html .= '<tbody>';
            foreach ($objects as $object) :
                $html .= ' <tr>';
                foreach ($object as $property => $value) :
                    $html .= '<td>';
                    $html .= $value;
                    $html .= '</td>';
                endforeach;

                $html .= '<td>';

                $html .= '<button type="button" class="btn btn-primary">Open</button>';
                $html .= '<button type="button" class="btn btn-secondary">Edit</button>';
                $html .= '<button type="button" class="btn btn-danger">Delete</button>';


                $html .= '</td>';

                $html .= '</tr>';
            endforeach;
            $html .= '</tbody></table></div>';
        }

        return $html;
    }


    private static function paginate($total_pages, $current_page){



/*        <nav aria-label="Page navigation example">
  <ul class="pagination">
    <li class="page-item"><a class="page-link" href="#">Previous</a></li>
    <li class="page-item"><a class="page-link" href="#">1</a></li>
    <li class="page-item"><a class="page-link" href="#">2</a></li>
    <li class="page-item"><a class="page-link" href="#">3</a></li>
    <li class="page-item"><a class="page-link" href="#">Next</a></li>
  </ul>
</nav>*/


    }

    private static function show_object_table_raw($object, $objects) {
        $html = '<div class="container"><table class="table">';
        $html .= '<thead>';
        foreach ($object as $property => $value) {
            if (is_array($value)) continue;
            $html .= '<th scope="col">' . $property . '</th>';
        }
        $html .= '</thead>';
        $html .= '<tbody>';
        foreach ($objects as $object) {
            $html .= ' <tr>';
            foreach ($object as $property => $value) {
                if (is_array($value)) {
                    continue;
                }
                $html .= '<td>';
                $html .= $value;
                $html .= '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
        return $html;
    }



    public static function data_table() {

    }
}
