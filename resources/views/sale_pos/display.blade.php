@extends('layouts.app')

@section('title', __('sale.pos_sale'))

@section('content')
    <section class="content no-print">
        <div class="row">
            <div class="col-md-12 tw-pt-0">
                <div
                    class="col-md-12 tw-shadow-[rgba(17,_17,_26,_0.1)_0px_0px_16px] tw-rounded-2xl tw-bg-white tw-mb-1 md:tw-mb-4">
                    {!! $pos_settings['display_screen_heading'] !!}
                </div>

                <div class="row pos_sell tw-flex lg:tw-flex-row md:tw-flex-col sm:tw-flex-col tw-flex-col tw-items-start md:tw-gap-4">

                    <div class="tw-px-3 lg:tw-px-0 lg:tw-pr-0 lg:tw-w-[60%] ">

                        <div
                            class="tw-shadow-[rgba(17,_17,_26,_0.1)_0px_0px_16px] tw-rounded-2xl tw-bg-white tw-mb-2 md:tw-mb-8 tw-p-2 !tw-h-[80vh]">
                            <div class="box-body pb-0">
                                <div class="row">
                                    <div class="col-md-7 customer_details">
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" title="{{ __('lang_v1.full_screen') }}"
                                            class="tw-shadow-[rgba(17,_17,_26,_0.1)_0px_0px_16px] tw-bg-white hover:tw-bg-white/60 tw-cursor-pointer tw-border-2 tw-flex tw-items-center tw-justify-center tw-rounded-md md:tw-w-8 tw-w-auto tw-h-8 tw-text-gray-600 pull-right !tw-ml-8"
                                            id="full_screen">
                                            <strong class="!tw-m-3">
                                                <i class="fa fa-window-maximize fa-lg tw-text-[#646EE4] !tw-text-sm"></i>
                                                <span class="tw-inline md:tw-hidden">Full Screen</span>
                                            </strong>
                                        </button>
                                    </div>
                                    <div class="col-sm-12 pos_product_div" style="height: 50vh !important;">
                                        <table class="table table-condensed table-bordered table-striped table-responsive"
                                            id="pos_table">
                                            <thead>
                                                <tr>
                                                    <th
                                                        class="tex-center tw-text-sm md:!tw-text-base tw-font-bold @if (!empty($pos_settings['inline_service_staff'])) col-md-3 @else col-md-4 @endif">
                                                        @lang('sale.product')
                                                        {{-- @show_tooltip(__('lang_v1.tooltip_sell_product_column')) --}}
                                                    </th>
                                                    <th
                                                        class="text-center tw-text-sm md:!tw-text-base tw-font-bold col-md-3">
                                                        @lang('sale.qty')
                                                    </th>
                                                    @if (!empty($pos_settings['inline_service_staff']))
                                                        <th
                                                            class="text-center tw-text-sm md:!tw-text-base tw-font-bold col-md-2">
                                                            @lang('restaurant.service_staff')
                                                        </th>
                                                    @endif
                                                    <th
                                                        class="text-center tw-text-sm md:!tw-text-base tw-font-bold col-md-2">
                                                        @lang('sale.price_inc_tax')
                                                    </th>
                                                    <th
                                                        class="text-center tw-text-sm md:!tw-text-base tw-font-bold col-md-2">
                                                        @lang('sale.subtotal')
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <table class="table table-condensed">
                                            <tr>
                                                <td>
                                                    <b
                                                        class="tw-text-base md:tw-text-lg tw-font-bold">@lang('sale.item'):</b>&nbsp;
                                                    <span
                                                        class="total_quantity tw-text-base md:tw-text-lg tw-font-semibold">0</span>
                                                </td>
                                                <td>
                                                    <b
                                                        class="tw-text-base md:tw-text-lg tw-font-bold">@lang('sale.total'):</b>&nbsp;
                                                    <span
                                                        class="price_total tw-text-base md:tw-text-lg tw-font-semibold ">0</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <b class="tw-text-base md:tw-text-lg tw-font-bold">@lang('sale.discount')
                                                        (-):</b>
                                                    <span
                                                        class="tw-text-base md:tw-text-lg tw-font-semibold"
                                                        id="total_discount">0</span>
                                                </td>
                                                <td>
                                                    <b class="tw-text-base md:tw-text-lg tw-font-bold">@lang('sale.order_tax')
                                                        (+):</b>
                                                    <span
                                                        class="tw-text-base md:tw-text-lg tw-font-semibold"
                                                         id="order_tax">0</span>
                                                </td>
                                                <td>
                                                    <b class="tw-text-base md:tw-text-lg tw-font-bold ">@lang('sale.shipping')
                                                        (+):</b>
                                                    <span
                                                        class="tw-text-base md:tw-text-lg tw-font-semibold "id="shipping_charges_amount">0</span>
                                                </td>
                                                <td>
                                                    <b
                                                        class="tw-text-base tw-text-green-900 tw-font-bold md:tw-text-2xl">@lang('sale.total_payable'):</b>
                                                    <span
                                                        class="tw-text-base tw-text-green-900 md:tw-text-2xl tw-font-semibold"
                                                        id="total_payable">0</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>

                                    <div class="col-md-12">
                                        <div class="box box-solid bg-orange">
                                            <div class="box-body">

                                                <div class="col-md-3">
                                                    <strong>
                                                        @lang('lang_v1.total_paying'):
                                                    </strong>
                                                    <br />
                                                    <span class="lead text-bold total_paying display_currency"
                                                        data-currency_symbol="true">0</span>
                                                </div>

                                                <div class="col-md-3">
                                                    <strong>
                                                        @lang('lang_v1.change_return'):
                                                    </strong>
                                                    <br />
                                                    <span class="lead text-bold change_return_span display_currency"
                                                        data-currency_symbol="true">0</span>
                                                </div>
                                                <div class="col-md-3">
                                                    <strong>
                                                        @lang('lang_v1.balance'):
                                                    </strong>
                                                    <br />
                                                    <span class="lead text-bold balance_due display_currency text-danger"
                                                        data-currency_symbol="true">0</span>
                                                </div>
                                            </div>
                                            <!-- /.box-body -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="md:tw-no-padding lg:tw-w-[40%] tw-px-5 !tw-h-[80vh] tw-shadow-xl tw-border tw-border-gray-400/30 tw-rounded-lg tw-flex tw-items-center tw-justify-center">
                        <div id="myCarousel" class="carousel slide !tw-h-full tw-w-full tw-transition-all tw-duration-500 tw-ease-in-out" data-ride="carousel">
                            <!-- Indicators -->
                            <ol class="carousel-indicators">
                                @foreach (range(1, 10) as $i)
                                    @if (isset($pos_settings['carousel_image_' . $i]))
                                        <li data-target="#myCarousel" data-slide-to="{{ $i - 1 }}" 
                                            class="tw-inline-block tw-mx-1 tw-border-2 !tw-border-black tw-rounded-full tw-w-4 tw-h-4 !tw-bg-white tw-opacity-90 tw-shadow-lg tw-cursor-pointer tw-transition-all tw-duration-300 hover:tw-bg-white hover:tw-opacity-100 {{ $i == 1 ? 'tw-bg-white tw-opacity-100' : 'tw-bg-gray-500' }}">
                                        </li>
                                    @endif
                                @endforeach
                            </ol>
                            <!-- Wrapper for slides -->
                            <div class="carousel-inner !tw-h-[80vh] tw-rounded-lg">
                                @foreach (range(1, 10) as $i)
                                    @if (isset($pos_settings['carousel_image_' . $i]))
                                        <div class="item {{ $i == 1 ? 'active' : '' }} !tw-h-full tw-flex tw-items-center tw-justify-center">
                                            <div class="tw-w-full tw-h-full tw-flex tw-items-center tw-justify-center">
                                                <img src="{{ url('uploads/carousel_images/' . $pos_settings['carousel_image_' . $i]) }}"
                                                    class="!tw-h-full !tw-w-full tw-object-contain tw-rounded-lg tw-transition-all tw-duration-500">
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@stop
@section('css')
    <!-- include module css -->
    @if (!empty($pos_module_data))
        @foreach ($pos_module_data as $key => $value)
            @if (!empty($value['module_css_path']))
                @includeIf($value['module_css_path'])
            @endif
        @endforeach
    @endif
@stop
@section('javascript')
    <script>
        $(document).ready(function() {
            let storageUpdateTimer = null; // Declare the timer globally

            // Simple in-memory caches
            const productsCache = new Map(); // `${variationId}__${locationId}` -> product|null

            function makeProductKey(variationId, locationId) {
                return `${variationId}__${locationId}`;
            }

            async function getCustomerById(id) {
                try {
                    const response = await $.ajax({
                        url: "/contacts/customers",
                        method: "GET",
                        dataType: "json",
                        delay: 250
                    });
                    const sid = String(id);
                    const filteredCustomers = (response || []).filter(function(customer) { return String(customer.id) === sid; });
                    return filteredCustomers.length ? filteredCustomers[0] : null;
                } catch (e) {
                    return null;
                }
            }

            function setProductInCache(variationId, locationId, product) {
                productsCache.set(makeProductKey(variationId, locationId), product);
            }

            async function fetchProductsBulkOnce(variationIds, locationId) {
                const missing = [];
                for (var i = 0; i < variationIds.length; i++) {
                    const vid = variationIds[i];
                    const key = makeProductKey(vid, locationId);
                    if (!productsCache.has(key)) missing.push(vid);
                }
                if (missing.length === 0) return;
                const qs = encodeURIComponent(missing.join(','));
                const url = `/pos/variations/bulk?ids=${qs}&location_id=${encodeURIComponent(locationId)}`;
                try {
                    const data = await $.ajax({
                        url: url,
                        method: 'GET',
                        dataType: 'json',
                        delay: 250
                    });
                    if (data && typeof data === 'object') {
                        Object.keys(data).forEach(function(id) {
                            setProductInCache(id, locationId, data[id]);
                        });
                    }
                } catch (e) {
                    // ignore network error; cache remains missing
                }
            }

            let isLoadingTableData = false; // Prevents multiple executions

            async function loadTableData() {
                if (isLoadingTableData) return; // Prevent simultaneous executions
                isLoadingTableData = true;

                const storedArrayData = JSON.parse(localStorage.getItem("pos_form_data_array"));

                // Check if stored data exists
                if (!storedArrayData) {
                    // console.warn("No stored form data found.");
                    isLoadingTableData = false;
                    return;
                }

                console.log("All data:", storedArrayData);


                const contactIdObj = storedArrayData.find((item) => item.name === "contact_id");
                const contactId = contactIdObj ? contactIdObj.value : null;

                const locationIdObj = storedArrayData.find((item) => item.name === "location_id");
                const location_id = locationIdObj ? locationIdObj.value : null;

                const final_total_item = storedArrayData.find((item) => item.name === "final_total");
                const final_total = final_total_item ? final_total_item.value : null;



                $("#total_payable").text(__currency_trans_from_en(__num_uf(final_total)));

                const discount_type_modal_item = storedArrayData.find((item) => item.name === "discount_type_modal");
                const discount_type_modal = discount_type_modal_item ? discount_type_modal_item.value : null;

                const discount_amount_modal_item = storedArrayData.find((item) => item.name ===
                    "discount_amount_modal");
                const discount_amount_modal = discount_amount_modal_item ? discount_amount_modal_item.value : null;


                const price_total_item = storedArrayData.find((item) => item.name === "price_total");
                const price_total = price_total_item ? price_total_item.value : null;


                $(".price_total").text(__currency_trans_from_en(price_total));



                // $("#total_discount").text(__calculate_amount(discount_type_modal, discount_amount_modal,
                //     price_total));

                // Step-by-step discount calculation with logs
            

                const computed_discount = __calculate_amount(
                    discount_type_modal,
                    discount_amount_modal,
                    price_total
                );


                $("#total_discount").text(
                    __currency_trans_from_en(computed_discount)
                );


                const order_tax_item = storedArrayData.find((item) => item.name === "order_tax");
                const order_tax = order_tax_item ? order_tax_item.value : null;


                $("#order_tax").text(__currency_trans_from_en((__num_uf(order_tax))));


                const shipping_charges_amount_item = storedArrayData.find((item) => item.name ===
                    "shipping_charges_amount");
                const shipping_charges_amount = shipping_charges_amount_item ? shipping_charges_amount_item.value : null;

                $("#shipping_charges_amount").text(__currency_trans_from_en(__num_uf(shipping_charges_amount)));


                const total_paying_input_item = storedArrayData.find((item) => item.name === "total_paying_input");
                const total_paying_input = total_paying_input_item ? total_paying_input_item.value : null;

                $(".total_paying").text(__num_uf(total_paying_input));


                const change_return_item = storedArrayData.find((item) => item.name === "change_return");
                const change_return = change_return_item ? change_return_item.value : null;
                $(".change_return_span").text(__num_uf(change_return));

                const in_balance_due_item = storedArrayData.find((item) => item.name === "in_balance_due");
                const in_balance_due = in_balance_due_item ? in_balance_due_item.value : null;
                $(".balance_due").text(__num_uf(in_balance_due));



                // Fetch customer details and update UI
                if (contactId) {
                    const customer = await getCustomerById(contactId);
                    if (customer) {
                        const name = customer.text || customer.name || "";
                        $(".customer_details").html(`<h3>${name}</h3>`);
                    }
                }

                let formattedData = {};

                // Parse and format data into a structured object
                storedArrayData.forEach(({
                    name,
                    value
                }) => {
                    let match = name.match(/products\[(\d+)\]\[(.*?)\]/);
                    if (match) {
                        let index = match[1]; // Extract product index (1, 2, etc.)
                        let key = match[2]; // Extract field name (e.g., product_type, unit_price)

                        if (!formattedData[index]) {
                            formattedData[index] = {};
                        }

                        formattedData[index][key] = value;
                    }
                });

                // Convert object into an array
                const resultArray = Object.values(formattedData).reverse();

                console.log("Formatted Product Data:", resultArray);

                // Select table body
                let tableBody = $("#pos_table tbody");

                // Clear existing table rows
                tableBody.empty();

                // One-time bulk fetch for all needed variations
                const neededVariationIds = [];
                resultArray.forEach(function(prod) {
                    if (prod && prod.variation_id) neededVariationIds.push(prod.variation_id);
                });
                await fetchProductsBulkOnce(neededVariationIds, location_id);

                let totalQuantity = 0;

                // Loop through formatted data and append rows to table
                for (let i = 0; i < resultArray.length; i++) {
                    const product = resultArray[i];
                    const single_product = productsCache.get(makeProductKey(product.variation_id, location_id)) || null;

                    // Determine product image URL
                    let imageUrl = `${base_path}/img/default.png`; // Default image
                    if (single_product && single_product.media && single_product.media.length > 0) {
                        imageUrl = single_product.media[0].display_url;
                    } else if (single_product && single_product.product_image) {
                        imageUrl = `${base_path}/uploads/img/${encodeURIComponent(single_product.product_image)}`;
                    }

                    const quantity = parseFloat(product.quantity) || 0;
                    totalQuantity = totalQuantity + quantity;

                    const unitPrice = __num_uf(product.unit_price_inc_tax);

                    const rowHtml = `
                        <tr>
                            <td class="text-left flex items-center">
                                <img loading="lazy" style="height:50px;display: inline;margin-left: 3px; border: black;border-radius: 5px; margin-top: 5px; width: 50px;object-fit: cover;" src="${imageUrl}" alt="Product Image" class="w-10 h-10 rounded mr-2"> <br/>
                                <span>${single_product ? single_product.product_name : "-"}</span>
                            </td> 
                            <td class="text-center">${product.quantity || "0"}</td>
                            <td class="text-center">${product.unit_price_inc_tax || "0.00"}</td>
                            <td class="text-center">${ __currency_trans_from_en(unitPrice, true) || "0.00"}</td>
                        </tr>
                    `;

                    tableBody.append(rowHtml);
                }
                $(".total_quantity").text(totalQuantity);
                isLoadingTableData = false; // Allow function to execute again
                console.log("Table updated with stored data.");
                __currency_convert_recursively($('.pos_sell'))
            }

            // Load table data initially
            loadTableData();

            // Debounce function to delay execution
            function debounceStorageUpdate() {
                clearTimeout(storageUpdateTimer);
                storageUpdateTimer = setTimeout(() => {
                    console.log("Debounced LocalStorage update: Reloading table...");
                    loadTableData();
                }, 400); // 400ms debounce time
            }
            // Prevent duplicate updates when localStorage changes rapidly
            window.onstorage = debounceStorageUpdate;
        });
    </script>
@endsection
