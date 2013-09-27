<?php
if (!defined("OR_VERSION")) {header("Location: /");exit(0);}

#
# HTML Pager extension
#
# Purpose:
# Simplify pagination.
#
# Usage:
#   # load this extension (if it is not always loaded)
#   load_extension("pager");
#   # get the base url
#   $link = get_url("some-named-route")."/";
#   # create a pager
#   $pager = pager($page_num, $items_per_page, $total_items_count, $link);
#   # load the items from the database (with limit and offset)
#   $items = database_model::get_paged_items($pager->limit(), $pager->offset());
#

function pager($offset, $per_page, $item_count, $link) {
    return new pager($offset, $per_page, $item_count, $link);
}

class pager
{
    private $offset         = null;
    private $per_page       = null;
    private $item_count     = null;
    private $active_item    = null;
    private $link           = null;
    private $link_count     = null;
    private $active_page    = null;

    function __construct($offset, $per_page, $item_count, $link) {
        $this->offset        = max(0, min($offset, $item_count-1));    # offset cannot exceed item count
        $this->per_page      = $per_page;
        $this->item_count    = $item_count;
        $this->link          = $link;
        # calculate the number of links on each side of the active page link, with a minimum of 2
        $this->link_count    = max(1, get_setting("pager", "link_count", 2));
        $this->active_page   = max(1, ceil(($this->offset + 1) / $this->per_page));
    }

    function limit() {return $this->per_page;}
    function offset() {return $this->offset;}

    private function _create_link($link_num, $page_num) {
        return "<a href=\"{$this->link}".(($link_num-1) * $this->per_page)."\"".
            (($this->active_page == $link_num) ? " class=\"activepage\"" : "").">{$page_num}</a>";
    }

    function __toString() {
        $body = "";
        if ($this->per_page > 0 && $this->item_count > 0) {
            $total_pages = ceil($this->item_count / $this->per_page);
            if ($total_pages > 1) {
                $spacer   = get_setting("pager", "spacer_text", "&#151;");
                $min_page = $this->active_page - $this->link_count;
                $max_page = min($this->active_page + $this->link_count, $total_pages);

                # fix min and max within sane range
                if ($min_page < 1) {
                    $max_page = $max_page - $min_page;
                    $min_page = 1;
                }
                if ($max_page > $total_pages) {
                    $max_page = $total_pages;
                }
                # nudge to stick to sides (ellipsis should hide more than one page link)
                if (3 == $min_page) {
                    if (($this->active_page + 1) < $max_page) {
                        # special case: only page 2 will be replaced by ellipsis, so snap to page 2
                        $min_page--;
                    }
                } else {
                    if ($max_page == ($total_pages - 2)) {
                        if ($this->active_page > ($min_page + 1)) {
                            # special case: only second-to-last page will be replace by ellipsis, so snap to that page
                            $max_page++;
                        }
                    }
                }

                $body .= "<span class=\"pager\">".get_setting("pager", "pager_text", "Page:&emsp;");
                if ($this->active_page > 1) {
                    $body .= "<a href=\"".$this->link.(($this->active_page - 2) * $this->per_page)."\" class=\"prev\">".
                        get_setting("pager", "prev_text", "&lang;")."</a>";
                } else {
                    $body .= "<span class=\"prev\">".get_setting("pager", "prev_text", "&lang;")."</span>";
                }
                if ($min_page > 1) {
                    $body .= $this->_create_link(1, 1, $this->active_page);
                    if ($min_page > 2) {
                        $body .= "<span>$spacer</span>";
                    }
                }
                for ($i = $min_page; $i <= $max_page; $i++) {
                    $body .= $this->_create_link($i, $i);
                }
                if ($max_page < $total_pages) {
                    if ($max_page < ($total_pages - 1)) {
                        $body .= "<span>$spacer</span>";
                    }
                    $body .= $this->_create_link($total_pages, $total_pages);
                }
                if ($this->active_page < $max_page) {
                    $body .= "<a href=\"".$this->link.($this->active_page * $this->per_page)."\" class=\"next\">".
                        get_setting("pager", "next_text", "&rang;")."</a>";
                } else {
                    $body .= "<span class=\"next\">".get_setting("pager", "next_text", "&rang;")."</span>";
                }
            }
        }
        return $body;
    }
}
