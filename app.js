// Define globals
const API_HOST = 'http://localhost/api.php'
let deleteItemId
let requestsTable
let summaryTable
let loading

// Function to toggle the visibility of the edit form
function toggleEditForm (id) {

  $('#editFormContainer').show()
  $('#requestsContainer').hide()
  $('#summaryContainer').hide()

  $('.editRequestItemsInputGroup').remove()

  // Form Create
  if (!id) {

    $('#editRequestedBy').val('')
    $('#editRequestId').val('')
    $('#itemType').val('')
    // Add a new item
    addEditRequestItem()
    return
  }

  loading.show()

  // Form Edit - Fetch data for the given id and populate form
  $.ajax({
    url: `${API_HOST}?_route=requests&id=${id}`, method: 'GET', success: function (data) {

      $('#editRequestedBy').val(data.data.requested_by)
      $('#editRequestId').val(data.data.id)

      data.data?.items?.forEach(item => {
        addEditRequestItem(item.id)
      })

      loading.hide()
    }, error: function () {
      loading.hide()
      console.error('Error fetching data for id: ' + id)
    }
  })
}

function cancelEditForm () {
  $('#editFormContainer').hide()
  $('#requestsContainer').show()
  $('#summaryContainer').show()
}

function deleteRequestModel () {

  $.ajax({
    url: `${API_HOST}?_route=requests&id=${deleteItemId}`,
    method: 'DELETE',
    success: function (response) {
      console.log('Item deleted successfully')

      // Close the confirmation modal
      $('#confirmationModal').modal('hide')

      // Reload the Summary DataTable
      requestsTable.ajax.reload()
      summaryTable.ajax.reload()
      $('.toast').toast('show')

    }, error: function (error) {
      console.error('Error deleting item:', error)
    }
  })
}

function showDeleteConfirmationModal (id) {

  deleteItemId = id

  $('#confirmationModal').modal('show')
}

function addEditRequestItem (selectedValue) {
  const newItemField = `
    <div class="input-group mb-2 editRequestItemsInputGroup">
      <select class="form-control editRequestItems" name="editRequestItems[]" required>
      </select>
      <div class="input-group-append">
        <button class="btn btn-outline-secondary" type="button" onclick="removeEditRequestItem(this)">
            <i class="fas fa-trash"></i>Remove</button>
      </div>
    </div>
  `
  $('#editRequestItemsGroup').append(newItemField)
  fetchOptionsForSelect($('.editRequestItems').last(), selectedValue) // Pass the last select element

}

// Function to remove an edit request item field
function removeEditRequestItem (button) {
  $(button).closest('.input-group').remove()
}

// Fetch options for the select field from the external API
function fetchOptionsForSelect (selectElement, selectedValue) {
  let itemType = $('#itemType').val() // Get the current item type

  let url = `${API_HOST}?_route=items`

  if (itemType) {
    url += `&item_type_id=${itemType}`
  }

  $.ajax({
    url: url,
    method: 'GET',
    success: function (response) {
      let options = response.data

      // Dynamically populate options for all select fields
      $(selectElement).empty()
      // $(selectElement).append(`<option value="" ${!!selectedValue ? 'selected' : ''} data-item-type-id=""></option>`)
      options.sort((a, b) => b.item_type_id - a.item_type_id) // b - a for reverse sort

      options.forEach(function (item) {
        const optName = `${item.item_type.name} >> ${item.name}`
        $(selectElement).append(`<option value="${item.id}" ${item.id === selectedValue ? 'selected' : ''} data-item-type-id="${item.item_type_id}">${optName}</option>`)
      })
    },
    error: function (error) {
      console.error('Error fetching options:', error)
    }
  })
}

$('#editForm').submit(function (event) {
  event.preventDefault()

  const formData = {
    requested_by: $('#editRequestedBy').val()
  }

  const id = $('#requestId').val()
  if (id) {
    formData.id = parseInt(id)
  }

  // Serialize array fields
  formData['items'] = $(this).find('select[name^="editRequestItems"]')
    .map(function () {
      return {
        id: parseInt(this.value)
      }
    })
    .get()

  // Check if there's an itemId
  const requestId = $('#editRequestId').val()

  let url = `${API_HOST}?_route=requests`
  let method = 'POST'

  // If itemId exists, it's an edit operation (PUT)
  if (requestId) {
    formData.id = parseInt(requestId)
    method = 'PUT'
  }
  loading.show()

  // Make an AJAX request to save
  $.ajax({
    url: url,
    method: method,
    contentType: 'application/json',
    data: JSON.stringify(formData),
    success: function (response) {
      console.log('Changes saved successfully')

      requestsTable.ajax.reload()
      summaryTable.ajax.reload()

      $('#editFormContainer').hide()
      $('#requestsContainer').show()
      $('#summaryContainer').show()
      loading.hide()

    },
    error: function (error) {
      loading.hide()
      console.error('Error saving changes:', error)
    }
  })
})

$(document).ready(function () {

  loading = $('#loading-overlay')

  requestsTable = $('#requestsTable').DataTable({
    paging: false, initComplete: function (settings, json) {
      loading.hide()
    }, ajax: {
      url: `${API_HOST}?_route=requests`, dataSrc: 'data'
    },
    processing: true,
    columns: [
      {
        data: 'id', render: function (data) {
          return `
            <div class="btn-group">
              <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-cog"></i>
              </button>
              <div class="dropdown-menu">
                <a class="dropdown-item" href="#" onclick="toggleEditForm(${data})"><i class="fas fa-edit"></i> Edit</a>
                <a class="dropdown-item" href="#" onclick="showDeleteConfirmationModal(${data})"><i class="fas fa-trash-alt"></i> Delete</a>
              </div>
            </div>
          `
        }
      },
      { data: 'requested_by' },
      { data: 'requested_on' },
      { data: 'ordered_on' },
      {
        data: 'items',
        render: function (items) {
          return items.map(item => item?.name).join(', ')
        }
      },
      {
        data: 'items',
        render: function (items) {
          let itemTypeName = ''
          items.forEach(item => itemTypeName = item.item_type?.name)
          return itemTypeName
        }
      },
    ],

  })

  summaryTable = $('#summaryTable').DataTable({
    paging: false, initComplete: function (settings, json) {
      loading.hide()
    }, ajax: {
      url: `${API_HOST}?_route=summary`, dataSrc: 'data'
    },
    processing: true,
    columns: [
      { data: 'requested_by' },
      { data: 'ordered_on' },
      {
        data: 'items',
        render: function (items) {

          return items.map(el => {

            let li = el?.items.map(elItem => {
              return `<li>${elItem?.name}</li>`
            })
            if (li) {
              li = `<ul>${li}</ul>`
            }
            return `<div>${el?.item_type?.name}</div>${li}`

          })
        }
      },

    ],

  })

  $('#requestsContainer').show()
  $('#summaryContainer').show()

})

$(document).on('change', '.editRequestItems:first', function () {

  // Update the #itemType value based on the selected item type
  const dt = $('.editRequestItems').first()
    .find(':selected')
    .attr('data-item-type-id')

  $('#itemType').val(dt)

  // Fetch and populate options for the subsequent editRequestItems select fields
  $('.editRequestItems').not(':first').each(function () {

    fetchOptionsForSelect($(this), $(this).val())
  })
})