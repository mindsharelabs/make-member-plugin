import gulp from "gulp";

import dartSass from 'sass'
import gulpSass from 'gulp-sass'
const sass = gulpSass(dartSass);

import sourcemaps from 'gulp-sourcemaps';
import {deleteAsync} from 'del';

gulp.task('plugin-styles', () => {
    return gulp.src('sass/style.scss')
      .pipe(sourcemaps.init())
      .pipe(sass({
        outputStyle: 'compressed'//nested, expanded, compact, compressed
      }).on('error', sass.logError))
      .pipe(sourcemaps.write('.'))
      .pipe(gulp.dest('./assets/css/'))
});

gulp.task('stats-styles', () => {
  return gulp.src('sass/stats.scss')
    .pipe(sourcemaps.init())
    .pipe(sass({
      outputStyle: 'compressed'//nested, expanded, compact, compressed
    }).on('error', sass.logError))
    .pipe(sourcemaps.write('.'))
    .pipe(gulp.dest('./assets/css/'))
});

gulp.task('slick-styles', () => {
  return gulp.src('sass/slick-theme.scss')
    .pipe(sourcemaps.init())
    .pipe(sass({
      outputStyle: 'compressed'//nested, expanded, compact, compressed
    }).on('error', sass.logError))
    .pipe(sourcemaps.write('.'))
    .pipe(gulp.dest('./assets/css/'))
});
//

gulp.task('clean', () => {
    return deleteAsync([
        'assets/css/style.css', 
        'assets/css/stats.css', 
        'assets/css/stats.css', 
    ]);
});

gulp.task('watch', () => {
  gulp.watch('sass/*.scss', (done) => {
    gulp.series(['plugin-styles'])(done);
    gulp.series(['slick-styles'])(done);
    gulp.series(['stats-styles'])(done);
  });
});

gulp.task('default', gulp.series(['clean', 'plugin-styles', 'stats-styles', 'slick-styles', 'watch']));
