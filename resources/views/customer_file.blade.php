<!doctype html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">

  <title>Hello, world!</title>
  <style>
    .MuiButton-root {
      color: rgba(0, 0, 0, 0.87);
      padding: 6px 16px;
      font-size: 0.875rem;
      min-width: 64px;
      box-sizing: border-box;
      transition: background-color 250ms cubic-bezier(0.4, 0, 0.2, 1) 0ms, box-shadow 250ms cubic-bezier(0.4, 0, 0.2, 1) 0ms, border 250ms cubic-bezier(0.4, 0, 0.2, 1) 0ms;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, Noto Sans TC, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
      font-weight: 500;
      line-height: 1.75;
      border-radius: 4px;
      text-transform: uppercase;

    }

    .MuiButton-fullWidth {
      width: 100%;
    }

    .MuiButton-text {
      padding: 6px 8px;
    }

    .MuiButtonBase-root {
      color: inherit;
      border: 0;
      cursor: pointer;
      margin: 0;
      display: inline-flex;
      outline: 0;
      padding: 0;
      position: relative;
      align-items: center;
      user-select: none;
      border-radius: 0;
      vertical-align: middle;
      -moz-appearance: none;
      justify-content: center;
      text-decoration: none;
      background-color: transparent;
      -webkit-appearance: none;
      -webkit-tap-highlight-color: transparent;
    }

    .MuiButton-label {
      width: 100%;
      display: inherit;
      align-items: inherit;
      justify-content: inherit;
    }

    .MuiTouchRipple-root {
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      z-index: 0;
      overflow: hidden;
      position: absolute;
      border-radius: inherit;
      pointer-events: none;
    }

    .makeStyles-helperText-6 {
      background: linear-gradient(0deg, #BAF2B5, #26AAD4);
      font-weight: bold;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      display: inline;
      text-align: center;
    }
  </style>
</head>

<body>
  <div class="row ">
    <div class="col-12 col-md-6 offset-md-3 p-0 mt-5">
      <form class="form-horizontal form-label-left" action="/customer/file/upload/save" method="post" enctype="multipart/form-data">

        <div class="mb-3">
          <label for="exampleFormControlInput1" class="form-label">請輸入商家ID(shop_id)</label>
          <input type="text" class="form-control" id="exampleFormControlInput1" name="shop_id" required>
        </div>

        <div class="mb-3">
          <label for="exampleInputEmail1" class="form-label">請選擇檔案</label>
          <div class="input-group mb-3">
            <input type="file" class="form-control" id="inputGroupFile02" name="file" required>
            <!-- <label class="input-group-text" for="inputGroupFile02">Upload</label> -->
          </div>
        </div>

        <input type="hidden" name="_token" value="{{ csrf_token() }}">
        <input type="submit" class="btn btn-primary" value="上傳">
      </form>
    </div>
  </div>



  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
  <script>
    // $('.test_form').submit();
  </script>

  <!-- Option 2: Separate Popper and Bootstrap JS -->
  <!--
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js" integrity="sha384-7+zCNj/IqJ95wo16oMtfsKbZ9ccEh31eOz1HGyDuCQ6wgnyJNSYdrPa03rtR1zdB" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js" integrity="sha384-QJHtvGhmr9XOIpI6YVutG+2QOK9T+ZnN4kzFN1RtK3zEFEIsxhlmWl5/YESvpZ13" crossorigin="anonymous"></script>
    -->
</body>

</html>