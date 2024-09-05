add_filter( 'ywcas_searching_result_data', 'gestisci_variazioni_per_categoria_con_fuzzyness', 10, 5 );

function gestisci_variazioni_per_categoria_con_fuzzyness( $search_result_data, $query_string, $post_type, $category, $lang ) {
    // Check if WooCommerce is active
    if ( ! class_exists( 'WooCommerce' ) ) {
        return $search_result_data;
    }

    $filtered_results = array();
    $processed_products = array();

    foreach ( $search_result_data as $result ) {
        $product_id = $result['post_id'];
        $product = wc_get_product( $product_id );
        
        if ( ! $product ) {
            continue;
        }

        $categories = $product->get_category_ids();

        // Handling for category 266
        if ( in_array( 266, $categories ) ) {
            // Only show the parent product
            if ( $product->get_type() !== 'variation' ) {
                $filtered_results[] = $result;
                $processed_products[] = $product_id;
            }
        } else {
            // For all other categories
            if ( $product->get_type() === 'variable' && !in_array($product_id, $processed_products) ) {
                $variations = $product->get_available_variations();

                foreach ( $variations as $variation ) {
                    $variation_product = wc_get_product( $variation['variation_id'] );
                    
                    if ( $variation_product ) {
                        $variation_result = array(
                            'post_id' => $variation['variation_id'],
                            'post_parent' => $product_id,
                            'name' => $variation_product->get_name(),
                            'url' => $variation_product->get_permalink(),
                            'thumbnail' => array(
                                'small' => wp_get_attachment_image_src( $variation_product->get_image_id(), 'thumbnail' )[0] ?? '',
                                'big' => wp_get_attachment_image_src( $variation_product->get_image_id(), 'full' )[0] ?? ''
                            ),
                            'price' => $variation_product->get_price_html(),
                            'sku' => $variation_product->get_sku(),
                            'product_type' => 'variation',
                            'score' => $result['score'],
                            'lang' => $lang
                        );

                        $filtered_results[] = $variation_result;
                    }
                }
                $processed_products[] = $product_id;
            } elseif ( $product->get_type() === 'variation' || !in_array($product_id, $processed_products) ) {
                // If it's already a variation or a simple product not yet processed, add it directly
                $filtered_results[] = $result;
                $processed_products[] = $product_id;
            }
        }
    }

    // Sort the results by score
    usort( $filtered_results, function( $a, $b ) {
        return $b['score'] <=> $a['score'];
    });

    return $filtered_results;
}

// Fuzziness
// Add fuzzy search logic also to the standard WooCommerce search results page
// Override fuzzy search behavior on WooCommerce search result pages
add_filter( 'ywcas_search_result_data', 'custom_fuzzy_search_for_woocommerce', 10, 4 );

function custom_fuzzy_search_for_woocommerce( $search_result_data, $query_string, $post_type, $lang ) {
    // Check if WooCommerce is active
    if ( ! class_exists( 'WooCommerce' ) ) {
        return $search_result_data;
    }

    // Get the current language
    $lang = ywcas_get_current_language();

    // If there are no exact results, activate fuzzy search
    if ( empty( $search_result_data ) ) {
        // Perform fuzzy search directly on the search page
        $search_engine = YITH_WCAS_Data_Search_Engine::get_instance();
        $best_token = $search_engine->get_fuzzy_query_string( [$query_string], $lang );

        if ( $best_token ) {
            // Get the results based on the fuzzy token
            $search_result_data = $search_engine->get_search_results( [$best_token], $post_type, 0, $lang );
        }
    }

    return $search_result_data;
}
