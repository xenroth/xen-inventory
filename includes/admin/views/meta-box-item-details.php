<?php
/**
 * Admin View: Item Details Meta Box.
 *
 * Variables available from MetaBoxes::render_item_details():
 *   $post           WP_Post
 *   $status         string   Current status key.
 *   $total_quantity int      Total quantity.
 *   $date_added     string   Y-m-d date string.
 *   $statuses       array    Allowed status labels.
 *
 * @package XenInventory\Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<table class="form-table xen-meta-table">
    <tbody>

        <tr>
            <th scope="row">
                <label for="xen_item_status"><?php esc_html_e( 'Status', 'xen-inventory' ); ?></label>
            </th>
            <td>
                <select id="xen_item_status" name="_xen_item_status">
                    <?php foreach ( $statuses as $key => $label ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="xen_total_quantity"><?php esc_html_e( 'Total Quantity', 'xen-inventory' ); ?></label>
            </th>
            <td>
                <input
                    type="number"
                    id="xen_total_quantity"
                    name="_xen_total_quantity"
                    value="<?php echo esc_attr( $total_quantity ); ?>"
                    min="0"
                    step="1"
                    class="small-text"
                />
                <p class="description"><?php esc_html_e( 'Total number of units in stock.', 'xen-inventory' ); ?></p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="xen_date_added"><?php esc_html_e( 'Date Added', 'xen-inventory' ); ?></label>
            </th>
            <td>
                <input
                    type="date"
                    id="xen_date_added"
                    name="_xen_date_added"
                    value="<?php echo esc_attr( $date_added ); ?>"
                />
            </td>
        </tr>

    </tbody>
</table>
