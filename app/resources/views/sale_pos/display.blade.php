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
                                    <div class="col-sm-12 pos_product_div">
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
                                                        class="price_total tw-text-base md:tw-text-lg tw-font-semibold display_currency"
                                                        data-currency_symbol="true">0</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <b class="tw-text-base md:tw-text-lg tw-font-bold">@lang('sale.discount')
                                                        (-):</b>
                                                    <span
                                                        class="tw-text-base md:tw-text-lg tw-font-semibold display_currency"
                                                        data-currency_symbol="true" id="total_discount">0</span>
                                                </td>
                                                <td>
                                                    <b class="tw-text-base md:tw-text-lg tw-font-bold">@lang('sale.order_tax')
                                                        (+):</b>
                                                    <span
                                                        class="tw-text-base md:tw-text-lg tw-font-semibold display_currency"
                                                        data-currency_symbol="true" id="order_tax">0</span>
                                                </td>
                                                <td>
                                                    <b class="tw-text-base md:tw-text-lg tw-font-bold ">@lang('sale.shipping')
                                                        (+):</b>
                                                    <span
                                                        class="tw-text-base md:tw-text-lg tw-font-semibold display_currency"
                                                        data-currency_symbol="true" id="shipping_charges_amount">0</span>
                                                </td>
                                                <td>
                                                    <b
                                                        class="tw-text-base tw-text-green-900 tw-font-bold md:tw-text-2xl">@lang('sale.total_payable'):</b>
                                                    <span
                                                        class="tw-text-base tw-text-green-900 md:tw-text-2xl tw-font-semibold display_currency"
                                                        data-currency_symbol="true" id="total_payable">0</span>
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
                    <div class="md:tw-no-padding lg:tw-w-[40%] tw-px-5 !tw-h-[80vh] tw-shadow-xl tw-border tw-border-gray-400/30 tw-rounded-lg">
                        <div id="myCarousel" class="carousel slide !tw-h-full tw-transition-all tw-duration-500 tw-ease-in-out" data-ride="carousel">
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
                                        <div class="item {{ $i == 1 ? 'active' : '' }} !tw-h-full tw-relative">
                                            <img src="{{ url('uploads/carousel_images/' . $pos_settings['carousel_image_' . $i]) }}"
                                                class="!tw-d-block !tw-mx-auto !tw-h-full !tw-w-full !tw-object-contain tw-rounded-lg tw-transition-all tw-duration-500">
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
            // Function to load and display data in the table
            async function fetchCustomers(id) {
                try {
                    let response = await $.ajax({
                        url: "/contacts/customers",
                        method: "GET",
                        dataType: "json",
                        delay: 250,
                    });

                    let filteredCustomers = response.filter((customer) => customer.id == id);
                    // console.log("Filtered Customers:", filteredCustomers);
                    return filteredCustomers;
                } catch (error) {
                    // console.error("Error fetching customer data:", error);
                    return [];
                }
            }

            async function fetchProduct(variation_id, location_id) {
                try {
                    let response = await $.ajax({
                        url: `/pos/variation/${variation_id}/${location_id}`,
                        method: "GET",
                        dataType: "json",
                        delay: 250,
                    });
                    console.log("Filtered single product:", response);
                    return response;
                } catch (error) {
                    console.error("Error fetching product data:", error);
                    return null;
                }
            }

            let isLoadingTableData = false; // Prevents multiple executions

            async function loadTableData() {
                if (isLoadingTableData) return; // Prevent simultaneous executions
                isLoadingTableData = true;

                var storedArrayData = JSON.parse(localStorage.getItem("pos_form_data_array"));

                // Check if stored data exists
                if (!storedArrayData) {
                    // console.warn("No stored form data found.");
                    return;
                }

                console.log("All data:", storedArrayData);


                var contactIdObj = storedArrayData.find((item) => item.name === "contact_id");
                var contactId = contactIdObj ? contactIdObj.value : null;

                var locationIdObj = storedArrayData.find((item) => item.name === "location_id");
                var location_id = locationIdObj ? locationIdObj.value : null;

                var final_total = storedArrayData.find((item) => item.name === "final_total");
                var final_total = final_total ? final_total.value : null;

                $("#total_payable").text(__currency_trans_from_en(final_total, false));

                var discount_type_modal = storedArrayData.find((item) => item.name === "discount_type_modal");
                var discount_type_modal = discount_type_modal ? discount_type_modal.value : null;

                var discount_amount_modal = storedArrayData.find((item) => item.name ===
                    "discount_amount_modal");
                var discount_amount_modal = discount_amount_modal ? discount_amount_modal.value : null;


                var price_total = storedArrayData.find((item) => item.name === "price_total");
                var price_total = price_total ? price_total.value : null;

                $(".price_total").text(__currency_trans_from_en(price_total, false));

                // $("#total_discount").text(__calculate_amount(discount_type_modal, discount_amount_modal,
                //     price_total));

                $("#total_discount").text(__currency_trans_from_en(__calculate_amount(discount_type_modal,
                    discount_amount_modal,
                    price_total), false));


                var order_tax = storedArrayData.find((item) => item.name === "order_tax");
                var order_tax = order_tax ? order_tax.value : null;
                $("#order_tax").text(__currency_trans_from_en(order_tax, false));


                var shipping_charges_amount = storedArrayData.find((item) => item.name ===
                    "shipping_charges_amount");
                var shipping_charges_amount = shipping_charges_amount ? shipping_charges_amount.value : null;

                $("#shipping_charges_amount").text(__currency_trans_from_en(shipping_charges_amount, false))


                var total_paying_input = storedArrayData.find((item) => item.name === "total_paying_input");
                var total_paying_input = total_paying_input ? total_paying_input.value : null;
                $(".total_paying").text(total_paying_input);


                var change_return = storedArrayData.find((item) => item.name === "change_return");
                var change_return = change_return ? change_return.value : null;
                $(".change_return_span").text(change_return);

                var in_balance_due = storedArrayData.find((item) => item.name === "in_balance_due");
                var in_balance_due = in_balance_due ? in_balance_due.value : null;
                $(".balance_due").text(in_balance_due);



                // Fetch customer details and update UI
                if (contactId) {
                    let customers = await fetchCustomers(contactId);
                    if (customers.length > 0) {
                        $(".customer_details").html(`<h3>${customers[0].text}</h3>`);
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
                let resultArray = Object.values(formattedData).reverse();

                console.log("Formatted Product Data:", resultArray);

                // Select table body
                let tableBody = $("#pos_table tbody");

                // Clear existing table rows
                tableBody.empty();

                let totalQuantity = 0;

                // Loop through formatted data and append rows to table

                tableBody.empty(); // Ensure this runs BEFORE the loop

                for (let product of resultArray) {
                    let single_product = await fetchProduct(product.variation_id, location_id);
                    // Determine product image URL
                    let imageUrl = `${base_path}/img/default.png`; // Default image
                    if (single_product && single_product.media && single_product.media.length > 0) {
                        imageUrl = single_product.media[0].display_url;
                    } else if (single_product && single_product.product_image) {
                        imageUrl =
                            `${base_path}/uploads/img/${encodeURIComponent(single_product.product_image)}`;
                    }

                    let quantity = parseFloat(product.quantity) || 0;

                    totalQuantity = totalQuantity + quantity;
                    let unitPrice = parseFloat((product.unit_price_inc_tax || "0").replace(/,/g, "")) || 0;

                    let rowHtml = `
                        <tr>
                            <td class="text-left flex items-center">
                                <img loading="lazy"style="height:50px;display: inline;margin-left: 3px; border: black;border-radius: 5px; margin-top: 5px; width: 50px;object-fit: cover;" src="${imageUrl}" alt="Product Image" class="w-10 h-10 rounded mr-2"> <br/>
                                <span>${single_product ? single_product.product_name : "-"}</span>
                            </td> 
                            <td class="text-center">${product.quantity || "0"}</td>
                            <td class="text-center display_currency" data-currency_symbol="true">${product.unit_price_inc_tax || "0.00"}</td>
                            <td class="text-center display_currency" data-currency_symbol="true">${__currency_trans_from_en((quantity * unitPrice).toFixed(2), false)}</td>
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
