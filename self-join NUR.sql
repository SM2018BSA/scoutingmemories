SELECT a.meta_value, b.meta_value
FROM wp_frm_item_metas a, wp_frm_item_metas b
WHERE a.item_id = b.item_id AND a.field_id = 439 AND b.field_id = 472

